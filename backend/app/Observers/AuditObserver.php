<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Feeds the activity journal from Eloquent model events.
 */
class AuditObserver
{
    /**
     * Business models whose lifecycle is worth journalling.
     *
     * @var list<class-string<Model>>
     */
    public const MODELS = [
        Client::class,
        Supplier::class,
        Product::class,
        PurchaseOrder::class,
        SalesOrder::class,
        Quote::class,
        Invoice::class,
        Payment::class,
    ];

    public function __construct(
        private readonly AuditLogger $logger,
    ) {}

    public function created(Model $model): void
    {
        $this->logger->record('created', $model);
    }

    public function updated(Model $model): void
    {
        $changes = Arr::except($model->getChanges(), ['updated_at']);

        if ($changes === []) {
            return;
        }

        $this->logger->record('updated', $model, $this->normalize($changes));
    }

    public function deleted(Model $model): void
    {
        $this->logger->record('deleted', $model);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function normalize(array $changes): array
    {
        return array_map(
            fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value),
            $changes,
        );
    }
}
