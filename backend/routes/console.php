<?php

use App\Services\InvoiceService;
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
