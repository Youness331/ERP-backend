<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $order->reference }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1f2937; }
        h1, h2 { margin: 0; color: #0f172a; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .header { width: 100%; }
        .header td { vertical-align: top; }
        .company-name { font-size: 15px; font-weight: bold; }
        .company-details { line-height: 1.55; }
        .doc-title { font-size: 20px; text-align: right; text-transform: uppercase; letter-spacing: 1px; }
        .doc-reference { font-size: 12px; text-align: right; margin-top: 4px; }
        .section { margin-top: 18px; }
        .section-title { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 4px; }
        .client { border: 1px solid #e5e7eb; padding: 10px 12px; width: 48%; }
        .lines { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .lines th { background: #0f172a; color: #ffffff; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.5px; padding: 7px 6px; text-align: left; }
        .lines td { border-bottom: 1px solid #e5e7eb; padding: 7px 6px; }
        .lines .num { text-align: right; white-space: nowrap; }
        .signatures { width: 100%; margin-top: 40px; }
        .signatures td { width: 50%; padding-top: 40px; border-top: 1px solid #9ca3af; font-size: 9px; color: #4b5563; }
        .footer { position: fixed; bottom: -8px; left: 0; right: 0; font-size: 8px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>

@php
    $quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', ' '), '0'), ',');
@endphp

<table class="header">
    <tr>
        <td style="width: 55%">
            <div class="company-name">{{ $company['name'] }}</div>
            <div class="company-details muted">
                {{ $company['address'] }}<br>
                {{ $company['city'] }}, {{ $company['country'] }}<br>
                Tél. {{ $company['phone'] }}<br>
                ICE {{ $company['ice'] }}
            </div>
        </td>
        <td style="width: 45%">
            <div class="doc-title">Bon de livraison</div>
            <div class="doc-reference">{{ $order->reference }}</div>
            <div class="right muted" style="margin-top: 6px">
                Date : {{ $order->order_date?->format('d/m/Y') }}<br>
                Livré le : {{ $order->delivered_at?->format('d/m/Y') ?? '—' }}
            </div>
        </td>
    </tr>
</table>

<div class="section">
    <div class="section-title">Livré à</div>
    <div class="client">
        <strong>{{ $order->client?->company_name ?: $order->client?->name }}</strong><br>
        <span class="muted">
            {{ $order->client?->address }}<br>
            {{ $order->client?->postal_code }} {{ $order->client?->city }}
        </span>
    </div>
</div>

<table class="lines">
    <thead>
        <tr>
            <th style="width: 52%">Désignation</th>
            <th class="num" style="width: 14%">Commandé</th>
            <th class="num" style="width: 14%">Livré</th>
            <th style="width: 20%">Unité</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->lines as $line)
            <tr>
                <td>
                    {{ $line->description }}
                    @if ($line->product?->sku)
                        <span class="muted">· {{ $line->product->sku }}</span>
                    @endif
                </td>
                <td class="num">{{ $quantity($line->quantity) }}</td>
                <td class="num">{{ $quantity($line->delivered_quantity) }}</td>
                <td>{{ $line->product?->unit }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="signatures">
    <tr>
        <td>Cachet et signature du client</td>
        <td class="right">Pour {{ $company['name'] }}</td>
    </tr>
</table>

<div class="footer">
    {{ $company['name'] }} — {{ $company['address'] }}, {{ $company['city'] }} ·
    ICE {{ $company['ice'] }} · IF {{ $company['tax_id'] }} · {{ $company['rc'] }}
</div>

</body>
</html>
