<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PurchaseOrderStatus;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregations used by the reporting screens.
 *
 * Every method returns plain arrays so the payload can be reshaped by the
 * Angular client without touching the query layer.
 */
class ReportService
{
    /**
     * @return array<string, mixed>
     */
    public function sales(CarbonInterface $from, CarbonInterface $to): array
    {
        $invoices = $this->issuedInvoices($from, $to);

        $count = (clone $invoices)->count();
        $revenue = (float) (clone $invoices)->sum('total');
        $tax = (float) (clone $invoices)->sum('tax_total');
        $discounts = (float) (clone $invoices)->sum('discount_total');

        return [
            'invoice_count' => $count,
            'revenue' => round($revenue, 2),
            'tax_collected' => round($tax, 2),
            'discounts_granted' => round($discounts, 2),
            'average_invoice' => $count > 0 ? round($revenue / $count, 2) : 0.0,
            'by_month' => $this->revenueByMonth($invoices),
            'top_products' => $this->topProducts($from, $to),
            'top_clients' => $this->topClients($from, $to),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function purchases(CarbonInterface $from, CarbonInterface $to): array
    {
        $orders = PurchaseOrder::query()
            ->whereIn('status', [
                PurchaseOrderStatus::Confirmed,
                PurchaseOrderStatus::PartiallyReceived,
                PurchaseOrderStatus::Received,
            ])
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()]);

        $count = (clone $orders)->count();
        $spend = (float) (clone $orders)->sum('total');
        $paid = (float) (clone $orders)->sum('amount_paid');

        return [
            'order_count' => $count,
            'spend' => round($spend, 2),
            'paid' => round($paid, 2),
            'outstanding' => round(max(0, $spend - $paid), 2),
            'by_supplier' => DB::table('purchase_orders')
                ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
                ->whereNull('purchase_orders.deleted_at')
                ->whereIn('purchase_orders.status', [
                    PurchaseOrderStatus::Confirmed->value,
                    PurchaseOrderStatus::PartiallyReceived->value,
                    PurchaseOrderStatus::Received->value,
                ])
                ->whereBetween('purchase_orders.order_date', [$from->toDateString(), $to->toDateString()])
                ->groupBy('suppliers.id', 'suppliers.name')
                ->orderByDesc(DB::raw('SUM(purchase_orders.total)'))
                ->limit(5)
                ->get([
                    'suppliers.id',
                    'suppliers.name',
                    DB::raw('SUM(purchase_orders.total) as spend'),
                    DB::raw('COUNT(*) as orders'),
                ])
                ->map(fn ($row) => [
                    'supplier_id' => (int) $row->id,
                    'supplier' => $row->name,
                    'spend' => round((float) $row->spend, 2),
                    'orders' => (int) $row->orders,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stockValuation(): array
    {
        $rows = DB::table('stocks')
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->whereNull('products.deleted_at')
            ->groupBy('warehouses.id', 'warehouses.name')
            ->orderBy('warehouses.name')
            ->get([
                'warehouses.id',
                'warehouses.name',
                DB::raw('COALESCE(SUM(stocks.quantity), 0) as quantity'),
                DB::raw('COALESCE(SUM(stocks.quantity * products.cost_price), 0) as value'),
            ])
            ->map(fn ($row) => [
                'warehouse_id' => (int) $row->id,
                'warehouse' => $row->name,
                'quantity' => round((float) $row->quantity, 3),
                'value' => round((float) $row->value, 2),
            ])
            ->all();

        return [
            'total_value' => round(array_sum(array_column($rows, 'value')), 2),
            'by_warehouse' => $rows,
        ];
    }

    /**
     * Outstanding receivables split into overdue buckets.
     *
     * @return array<string, mixed>
     */
    public function receivablesAging(): array
    {
        $buckets = [
            'current' => ['key' => 'current', 'label' => 'Non échu', 'amount' => 0.0, 'count' => 0],
            'days_1_30' => ['key' => 'days_1_30', 'label' => '1 à 30 jours', 'amount' => 0.0, 'count' => 0],
            'days_31_60' => ['key' => 'days_31_60', 'label' => '31 à 60 jours', 'amount' => 0.0, 'count' => 0],
            'days_61_90' => ['key' => 'days_61_90', 'label' => '61 à 90 jours', 'amount' => 0.0, 'count' => 0],
            'over_90' => ['key' => 'over_90', 'label' => 'Plus de 90 jours', 'amount' => 0.0, 'count' => 0],
        ];

        $invoices = Invoice::query()
            ->outstanding()
            ->get(['due_date', 'balance_due']);

        foreach ($invoices as $invoice) {
            $daysOverdue = (int) $invoice->due_date->diffInDays(today(), false);

            $key = match (true) {
                $daysOverdue <= 0 => 'current',
                $daysOverdue <= 30 => 'days_1_30',
                $daysOverdue <= 60 => 'days_31_60',
                $daysOverdue <= 90 => 'days_61_90',
                default => 'over_90',
            };

            $buckets[$key]['amount'] = round($buckets[$key]['amount'] + (float) $invoice->balance_due, 2);
            $buckets[$key]['count']++;
        }

        return [
            'total' => round(array_sum(array_column($buckets, 'amount')), 2),
            'invoice_count' => $invoices->count(),
            'buckets' => array_values($buckets),
        ];
    }

    private function issuedInvoices(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Invoice::query()
            ->where('type', InvoiceType::Invoice->value)
            ->whereIn('status', [
                InvoiceStatus::Sent,
                InvoiceStatus::PartiallyPaid,
                InvoiceStatus::Paid,
                InvoiceStatus::Overdue,
            ])
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * @return array<int, array{month: string, total: float}>
     */
    private function revenueByMonth(Builder $invoices): array
    {
        $months = [];

        foreach ((clone $invoices)->get(['issue_date', 'total']) as $invoice) {
            $key = $invoice->issue_date->format('Y-m');
            $months[$key] = round(($months[$key] ?? 0) + (float) $invoice->total, 2);
        }

        ksort($months);

        $series = [];

        foreach ($months as $month => $total) {
            $series[] = ['month' => $month, 'total' => $total];
        }

        return $series;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topProducts(CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->join('products', 'products.id', '=', 'invoice_lines.product_id')
            ->whereNull('invoices.deleted_at')
            ->whereIn('invoices.status', $this->issuedStatuses())
            ->whereBetween('invoices.issue_date', [$from->toDateString(), $to->toDateString()])
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
                'product_id' => (int) $row->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'quantity' => round((float) $row->quantity, 3),
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topClients(CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('invoices')
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->whereNull('invoices.deleted_at')
            ->whereIn('invoices.status', $this->issuedStatuses())
            ->whereBetween('invoices.issue_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('clients.id', 'clients.name')
            ->orderByDesc(DB::raw('SUM(invoices.total)'))
            ->limit(5)
            ->get([
                'clients.id',
                'clients.name',
                DB::raw('SUM(invoices.total) as revenue'),
                DB::raw('COUNT(*) as invoices'),
            ])
            ->map(fn ($row) => [
                'client_id' => (int) $row->id,
                'client' => $row->name,
                'revenue' => round((float) $row->revenue, 2),
                'invoices' => (int) $row->invoices,
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function issuedStatuses(): array
    {
        return [
            InvoiceStatus::Sent->value,
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::Paid->value,
            InvoiceStatus::Overdue->value,
        ];
    }
}
