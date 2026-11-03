<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; font-size: 14px; line-height: 1.6">
    <p>Bonjour {{ $invoice->client?->company_name ?: $invoice->client?->name }},</p>

    <p>
        Veuillez trouver ci-joint la facture <strong>{{ $invoice->reference }}</strong>
        d'un montant de
        <strong>{{ number_format((float) $invoice->total, 2, ',', ' ') }} {{ config('erp.currency.symbol') }}</strong>,
        émise le {{ $invoice->issue_date?->format('d/m/Y') }}
        et échéant le {{ $invoice->due_date?->format('d/m/Y') }}.
    </p>

    @if ((float) $invoice->balance_due < (float) $invoice->total)
        <p>
            Montant déjà réglé : {{ number_format((float) $invoice->amount_paid, 2, ',', ' ') }} {{ config('erp.currency.symbol') }}<br>
            Reste à payer : <strong>{{ number_format((float) $invoice->balance_due, 2, ',', ' ') }} {{ config('erp.currency.symbol') }}</strong>
        </p>
    @endif

    <p>
        Pour toute question, répondez simplement à ce message ou appelez le
        {{ config('erp.company.phone') }}.
    </p>

    <p>
        Cordialement,<br>
        <strong>{{ config('erp.company.name') }}</strong>
    </p>

    <p style="font-size: 11px; color: #6b7280">
        {{ config('erp.company.address') }}, {{ config('erp.company.city') }} ·
        ICE {{ config('erp.company.ice') }} · IF {{ config('erp.company.tax_id') }}
    </p>
</body>
</html>
