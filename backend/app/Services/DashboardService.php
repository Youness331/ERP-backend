<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentDirection;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates the figures shown on the operations dashboard.
 */
class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth();
        $previousStart = $now->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $now->subMonthNoOverflow()->endOfMonth();

        $revenue = $this->invoicedBetween($monthStart, $monthEnd);
        $previousRevenue = $this->invoicedBetween($previousStart, $previousEnd);
        $collected = (float) Payment::query()
            ->where('direction', PaymentDirection::Incoming->value)
            ->whereBetween('payment_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('amount');

        $receivables = (float) Invoice::query()->outstanding()->sum('balance_due');
        $overdueQuery = Invoice::query()->outstanding()->whereDate('due_date', '<', $now->toDateString());
        $overdueCount = (clone $overdueQuery)->count();
        $overdueAmount = (float) (clone $overdueQuery)->sum('balance_due');

        return [
            'period' => [
                'from' => $monthStart->toDateString(),
                'to' => $monthEnd->toDateString(),
            ],
            'revenue' => [
                'current_month' => round($revenue, 2),
                'previous_month' => round($previousRevenue, 2),
                'growth_percent' => $previousRevenue > 0
                    ? round((($revenue - $previousRevenue) / $previousRevenue) * 100, 1)
                    : null,
                'collected_this_month' => round($collected, 2),
            ],
            'receivables' => [
                'outstanding' => round($receivables, 2),
                'overdue_amount' => round($overdueAmount, 2),
                'overdue_count' => $overdueCount,
            ],
            'sales' => [
                'open_orders' => SalesOrder::query()->open()->count(),
                'to_deliver' => SalesOrder::query()
                    ->whereIn('status', [SalesOrderStatus::Confirmed, SalesOrderStatus::PartiallyDelivered])
                    ->count(),
                'orders_this_month' => SalesOrder::query()
                    ->whereBetween('order_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->count(),
            ],
            'purchasing' => [
                'pending_orders' => PurchaseOrder::query()
                    ->whereIn('status', [PurchaseOrderStatus::Confirmed, PurchaseOrderStatus::PartiallyReceived])
                    ->count(),
                'orders_this_month' => PurchaseOrder::query()
                    ->whereBetween('order_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->count(),
            ],
            'inventory' => [
                'low_stock_count' => Product::query()->lowStock()->count(),
                'stock_value' => round($this->stockValue(), 2),
                'product_count' => Product::query()->count(),
            ],
            'partners' => [
                'clients' => Client::query()->count(),
                'suppliers' => Supplier::query()->count(),
            ],
            'charts' => [
                'sales_last_12_months' => $this->salesSeries($now),
            ],
            'top_products' => $this->topProducts($now),
            'low_stock_products' => $this->lowStockProducts(),
            'recent_invoices' => $this->recentInvoices(),
        ];
    }

    private function invoicedBetween(CarbonImmutable $from, CarbonImmutable $to): float
    {
        return (float) Invoice::query()
            ->where('type', InvoiceType::Invoice->value)
            ->whereIn('status', [
                InvoiceStatus::Sent,
                InvoiceStatus::PartiallyPaid,
                InvoiceStatus::Paid,
                InvoiceStatus::Overdue,
            ])
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->sum('total');
    }

    private function stockValue(): float
    {
        return (float) DB::table('stocks')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereNull('products.deleted_at')
            ->selectRaw('COALESCE(SUM(stocks.quantity * products.cost_price), 0) as value')
            ->value('value');
    }

    /**
     * @return array<int, array{month: string, label: string, total: float}>
     */
    private function salesSeries(CarbonImmutable $now): array
    {
        $series = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = $now->subMonthsNoOverflow($i);
            $key = $month->format('Y-m');

            $series[$key] = [
                'month' => $key,
                'label' => $month->translatedFormat('M Y'),
                'total' => 0.0,
            ];
        }

        $invoices = Invoice::query()
            ->where('type', InvoiceType::Invoice->value)
            ->whereIn('status', [
                InvoiceStatus::Sent,
                InvoiceStatus::PartiallyPaid,
                InvoiceStatus::Paid,
                InvoiceStatus::Overdue,
            ])
            ->where('issue_date', '>=', $now->subMonthsNoOverflow(11)->startOfMonth()->toDateString())
            ->get(['issue_date', 'total']);

        foreach ($invoices as $invoice) {
            $key = $invoice->issue_date->format('Y-m');

            if (isset($series[$key])) {
                $series[$key]['total'] = round($series[$key]['total'] + (float) $invoice->total, 2);
            }
        }

        return array_values($series);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topProducts(CarbonImmutable $now): array
    {
        return DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->join('products', 'products.id', '=', 'invoice_lines.product_id')
            ->whereNull('invoices.deleted_at')
            ->whereIn('invoices.status', [
                InvoiceStatus::Sent->value,
                InvoiceStatus::PartiallyPaid->value,
                InvoiceStatus::Paid->value,
                InvoiceStatus::Overdue->value,
            ])
            ->where('invoices.issue_date', '>=', $now->subDays(90)->toDateString())
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc(DB::raw('SUM(invoice_lines.quantity)'))
            ->limit(5)
            ->get([
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(invoice_lines.quantity) as quantity'),
                DB::raw('COALESCE(SUM(invoice_lines.line_total), 0) as revenue'),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'quantity' => (float) $row->quantity,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lowStockProducts(): array
    {
        return Product::query()
            ->lowStock()
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'sku', 'name', 'reorder_level'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'reorder_level' => (float) $product->reorder_level,
                'quantity' => round((float) $product->stocks()->sum('quantity'), 3),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentInvoices(): array
    {
        return Invoice::query()
            ->with('client:id,name')
            ->latest('issue_date')
            ->latest('id')
            ->limit(5)
            ->get(['id', 'reference', 'client_id', 'status', 'issue_date', 'total', 'balance_due'])
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'reference' => $invoice->reference,
                'client' => $invoice->client?->name,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'issue_date' => $invoice->issue_date->toDateString(),
                'total' => (float) $invoice->total,
                'balance_due' => (float) $invoice->balance_due,
            ])
            ->all();
    }
}
