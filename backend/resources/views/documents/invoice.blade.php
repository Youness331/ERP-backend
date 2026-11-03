<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->reference }}</title>
    <style>
        @page { margin: 28px 32px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1f2937; }
        h1, h2, h3 { margin: 0; color: #0f172a; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .header { width: 100%; }
        .header td { vertical-align: top; }
        .company-name { font-size: 15px; font-weight: bold; }
        .company-details { line-height: 1.55; }
        .doc-title { font-size: 20px; text-align: right; text-transform: uppercase; letter-spacing: 1px; }
        .doc-reference { font-size: 12px; text-align: right; margin-top: 4px; }
        .doc-meta { width: 100%; margin-top: 6px; }
        .doc-meta td { border: none; padding: 2px 0; font-size: 10px; }
        .section { margin-top: 18px; }
        .section-title { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 4px; }
        .client { border: 1px solid #e5e7eb; padding: 10px 12px; width: 48%; }
        .client strong { font-size: 11px; }
        .lines { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .lines th { background: #0f172a; color: #ffffff; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.5px; padding: 7px 6px; text-align: left; }
        .lines td { border-bottom: 1px solid #e5e7eb; padding: 7px 6px; }
        .lines .num { text-align: right; white-space: nowrap; }
        .totals { width: 46%; margin-left: 54%; margin-top: 12px; border-collapse: collapse; }
        .totals td { padding: 4px 6px; }
        .totals .label { color: #4b5563; }
        .totals .value { text-align: right; white-space: nowrap; }
        .totals .grand td { border-top: 1.5px solid #0f172a; font-size: 12px; font-weight: bold; padding-top: 7px; }
        .badge { display: inline-block; padding: 2px 7px; border: 1px solid #0f172a; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.6px; }
        .footer { position: fixed; bottom: -8px; left: 0; right: 0; font-size: 8px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 6px; }
        .notes { margin-top: 16px; font-size: 9px; color: #4b5563; }
    </style>
</head>
<body>

@php
    $money = fn ($value) => number_format((float) $value, 2, ',', ' ');
    $symbol = config('erp.currency.symbol');
@endphp

<table class="header">
    <tr>
        <td style="width: 55%">
            <div class="company-name">{{ $company['name'] }}</div>
            <div class="company-details muted">
                {{ $company['address'] }}<br>
                {{ $company['city'] }}, {{ $company['country'] }}<br>
                Tél. {{ $company['phone'] }} — {{ $company['email'] }}<br>
                ICE {{ $company['ice'] }} · IF {{ $company['tax_id'] }} · {{ $company['rc'] }}
            </div>
        </td>
        <td style="width: 45%">
            <div class="doc-title">{{ $invoice->type->label() }}</div>
            <div class="doc-reference">{{ $invoice->reference }}</div>
            <table class="doc-meta">
                <tr>
                    <td class="muted right">Date d'émission</td>
                    <td class="right">{{ $invoice->issue_date?->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="muted right">Échéance</td>
                    <td class="right">{{ $invoice->due_date?->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td class="muted right">Statut</td>
                    <td class="right"><span class="badge">{{ $invoice->status->label() }}</span></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="section">
    <div class="section-title">Facturé à</div>
    <div class="client">
        <strong>{{ $invoice->client?->company_name ?: $invoice->client?->name }}</strong><br>
        <span class="muted">
            {{ $invoice->client?->address }}<br>
            {{ $invoice->client?->postal_code }} {{ $invoice->client?->city }}<br>
            @if ($invoice->client?->ice) ICE {{ $invoice->client->ice }}@endif
        </span>
    </div>
</div>

<table class="lines">
    <thead>
        <tr>
            <th style="width: 46%">Désignation</th>
            <th class="num" style="width: 10%">Qté</th>
            <th class="num" style="width: 14%">P.U. ({{ $symbol }})</th>
            <th class="num" style="width: 10%">Remise</th>
            <th class="num" style="width: 8%">TVA</th>
            <th class="num" style="width: 12%">Total HT</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->lines as $line)
            <tr>
                <td>
                    {{ $line->description }}
                    @if ($line->product?->sku)
                        <span class="muted">· {{ $line->product->sku }}</span>
                    @endif
                </td>
                <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 3, ',', ' '), '0'), ',') }}</td>
                <td class="num">{{ $money($line->unit_price) }}</td>
                <td class="num">{{ $money($line->discount) }}</td>
                <td class="num">{{ rtrim(rtrim(number_format((float) $line->tax_rate, 2, ',', ''), '0'), ',') }} %</td>
                <td class="num">{{ $money($line->line_total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td class="label">Sous-total HT</td>
        <td class="value">{{ $money($invoice->subtotal) }} {{ $symbol }}</td>
    </tr>
    <tr>
        <td class="label">Remises</td>
        <td class="value">- {{ $money($invoice->discount_total) }} {{ $symbol }}</td>
    </tr>
    <tr>
        <td class="label">TVA</td>
        <td class="value">{{ $money($invoice->tax_total) }} {{ $symbol }}</td>
    </tr>
    <tr class="grand">
        <td class="label">Total TTC</td>
        <td class="value">{{ $money($invoice->total) }} {{ $symbol }}</td>
    </tr>
    <tr>
        <td class="label">Déjà réglé</td>
        <td class="value">{{ $money($invoice->amount_paid) }} {{ $symbol }}</td>
    </tr>
    <tr>
        <td class="label">Reste à payer</td>
        <td class="value">{{ $money($invoice->balance_due) }} {{ $symbol }}</td>
    </tr>
</table>

@if ($invoice->notes)
    <div class="notes">
        <strong>Notes</strong><br>
        {{ $invoice->notes }}
    </div>
@endif

<div class="footer">
    {{ $company['name'] }} — {{ $company['address'] }}, {{ $company['city'] }} ·
    ICE {{ $company['ice'] }} · IF {{ $company['tax_id'] }} · {{ $company['rc'] }} · {{ $company['patente'] }}
</div>

</body>
</html>
