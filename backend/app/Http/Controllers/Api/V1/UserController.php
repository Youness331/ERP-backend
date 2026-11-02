<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()->with('role');

        if ($request->filled('search')) {
            $term = '%'.$request->query('search').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->integer('role_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return UserResource::collection($query->orderBy('name')->paginate($this->perPage($request)));
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $user = User::create($request->validated());

        return new UserResource($user->load('role'));
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('role.permissions'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return new UserResource($user->load('role'));
    }

    public function destroy(Request $request, User $user): Response
    {
        if ($request->user()->is($user)) {
            throw new BusinessException('Vous ne pouvez pas supprimer votre propre compte.');
        }

        if ($user->isAdmin() && User::query()->whereHas('role', fn ($q) => $q->where('name', 'admin'))->count() <= 1) {
            throw new BusinessException('Le dernier administrateur ne peut pas être supprimé.');
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
