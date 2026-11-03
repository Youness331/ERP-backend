<?php

use App\Services\InvoiceService;
use App\Services\QuoteService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
