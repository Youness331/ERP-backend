<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Services\ReferenceGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ClientController extends Controller
{
    public function __construct(private readonly ReferenceGenerator $references) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Client::query()
            ->withCount(['salesOrders', 'invoices'])
            ->search($request->query('search'));

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sort = in_array($request->query('sort'), ['name', 'code', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        return ClientResource::collection(
            $query->orderBy($sort, $direction)->paginate($this->perPage($request))
        );
    }

    public function store(StoreClientRequest $request): ClientResource
    {
        $data = $request->validated();
        $data['code'] ??= $this->references->next('client', 'CLI');
        $data['created_by'] = $request->user()->id;

        $client = Client::create($data);

        return new ClientResource($client);
    }

    public function show(Client $client): ClientResource
    {
        return new ClientResource($client->loadCount(['salesOrders', 'invoices']));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $client->update($request->validated());

        return new ClientResource($client);
    }

    public function destroy(Client $client): Response
    {
        if ($client->salesOrders()->open()->exists()) {
            throw new BusinessException('Impossible de supprimer un client ayant des commandes en cours.');
        }

        if ($client->invoices()->outstanding()->exists()) {
            throw new BusinessException('Impossible de supprimer un client ayant des factures impayées.');
        }

        $client->delete();

        return response()->noContent();
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
