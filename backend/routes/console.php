<?php

use App\Mail\StockAlertMail;
use App\Models\Product;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('erp:refresh-overdue-invoices', function (InvoiceService $invoices) {
    $updated = $invoices->markOverdue();

    $this->info(sprintf('%d facture(s) marquée(s) en retard.', $updated));

    return self::SUCCESS;
})->purpose('Flag unpaid invoices that passed their due date');

Schedule::command('erp:refresh-overdue-invoices')->dailyAt('01:00');

Artisan::command('erp:expire-quotes', function (QuoteService $quotes) {
    $expired = $quotes->expireOutdated();

    $this->info(sprintf('%d devis marqué(s) expiré(s).', $expired));

    return self::SUCCESS;
})->purpose('Expire quotes past their validity date');

Schedule::command('erp:expire-quotes')->dailyAt('01:15');

Artisan::command('erp:stock-alerts', function () {
    $products = Product::query()
        ->lowStock()
        ->orderBy('name')
        ->get(['id', 'sku', 'name', 'reorder_level']);

    if ($products->isEmpty()) {
        $this->info('Aucun produit sous le seuil de réapprovisionnement.');

        return self::SUCCESS;
    }

    Mail::to(config('erp.alerts.email'))->send(new StockAlertMail($products));

    $this->info(sprintf('%d produit(s) signalé(s).', $products->count()));

    return self::SUCCESS;
})->purpose('Email the products that fell below their reorder level');

Schedule::command('erp:stock-alerts')->dailyAt('07:00');
