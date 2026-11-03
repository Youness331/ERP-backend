<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Product;
use App\Models\Quote;
use App\Models\SalesOrder;
use App\Models\User;
use App\Support\LineItems;
use App\Support\LineTotals;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Drives the devis lifecycle: draft -> sent -> accepted/rejected -> converted.
 */
class QuoteService
{
    public function __construct(
        private readonly ReferenceGenerator $references,
        private readonly SalesOrderService $salesOrders,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user = null): Quote
    {
        return DB::transaction(function () use ($data, $user) {
            $lines = $this->prepareLines($data['lines']);
            $totals = LineTotals::summarize($lines);

            $quote = Quote::create([
                'reference' => $this->references->next('quote', 'DEV'),
                'client_id' => $data['client_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => QuoteStatus::Draft,
                'quote_date' => $data['quote_date'] ?? today(),
                'valid_until' => $data['valid_until']
                    ?? today()->addDays((int) config('erp.quote.valid_days', 30)),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id ?? Auth::id(),
            ] + $totals);

            $this->replaceLines($quote, $lines);

            return $quote->load(['client', 'warehouse', 'lines.product']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Quote $quote, array $data): Quote
    {
        if (! $quote->status->canBeEdited()) {
            throw InvalidStatusTransitionException::from('devis', $quote->status->label(), 'modifier');
        }

        return DB::transaction(function () use ($quote, $data) {
            $quote->fill(array_intersect_key($data, array_flip([
                'client_id', 'warehouse_id', 'quote_date', 'valid_until', 'notes',
            ])));

            if (isset($data['lines'])) {
                $lines = $this->prepareLines($data['lines']);
                $this->replaceLines($quote, $lines);
                $quote->fill(LineTotals::summarize($lines));
            }

            $quote->save();

            return $quote->load(['client', 'warehouse', 'lines.product']);
        });
    }

    public function send(Quote $quote): Quote
    {
        if ($quote->status === QuoteStatus::Draft) {
            $quote->status = QuoteStatus::Sent;
            $quote->save();
        }

        if ($quote->status !== QuoteStatus::Sent) {
            throw InvalidStatusTransitionException::from('devis', $quote->status->label(), 'envoyer');
        }

        return $quote->fresh(['client', 'warehouse', 'lines.product']);
    }

    public function accept(Quote $quote): Quote
    {
        return $this->decide($quote, QuoteStatus::Accepted);
    }

    public function reject(Quote $quote): Quote
    {
        return $this->decide($quote, QuoteStatus::Rejected);
    }

    /**
     * Turns an accepted quote into a draft sales order.
     */
    public function convert(Quote $quote, ?User $user = null): SalesOrder
    {
        $quote->loadMissing('lines.product');

        if ($quote->hasBeenConverted()) {
            throw new BusinessException('Ce devis a déjà été converti en commande.');
        }

        if (! $quote->status->isConvertible()) {
            throw new BusinessException('Seul un devis accepté peut être converti en commande.');
        }

        return DB::transaction(function () use ($quote, $user) {
            $order = $this->salesOrders->create([
                'client_id' => $quote->client_id,
                'warehouse_id' => $quote->warehouse_id,
                'order_date' => today()->toDateString(),
                'notes' => sprintf('Issu du devis %s', $quote->reference),
                'lines' => $quote->lines->map(fn ($line) => [
                    'product_id' => $line->product_id,
                    'description' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'unit_price' => (float) $line->unit_price,
                    'discount' => (float) $line->discount,
                    'tax_rate' => (float) $line->tax_rate,
                ])->all(),
            ], $user);

            $quote->status = QuoteStatus::Converted;
            $quote->sales_order_id = $order->id;
            $quote->converted_at = now();
            $quote->save();

            return $order;
        });
    }

    /**
     * Marks sent quotes whose validity window has passed. Returns the count.
     */
    public function expireOutdated(): int
    {
        return Quote::query()
            ->whereIn('status', [QuoteStatus::Draft->value, QuoteStatus::Sent->value])
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', today())
            ->update(['status' => QuoteStatus::Expired->value]);
    }

    private function decide(Quote $quote, QuoteStatus $target): Quote
    {
        if (! $quote->status->isDecidable()) {
            throw InvalidStatusTransitionException::from('devis', $quote->status->label(), 'décider');
        }

        $quote->status = $target;
        $quote->save();

        return $quote->fresh(['client', 'warehouse', 'lines.product']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function prepareLines(array $lines): array
    {
        return LineItems::prepare($lines, fn (Product $product) => (float) $product->sale_price);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function replaceLines(Quote $quote, array $lines): void
    {
        $quote->lines()->delete();

        foreach ($lines as $line) {
            $quote->lines()->create($line);
        }

        $quote->unsetRelation('lines');
    }
}
