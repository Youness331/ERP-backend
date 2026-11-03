<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; font-size: 14px; line-height: 1.6">
    <p>Bonjour,</p>

    <p>
        Les produits suivants sont au niveau ou en dessous de leur seuil de
        réapprovisionnement :
    </p>

    <table cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse; font-size: 13px">
        <thead>
            <tr style="background: #0f172a; color: #ffffff">
                <th align="left">SKU</th>
                <th align="left">Produit</th>
                <th align="right">Seuil</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($products as $product)
                <tr>
                    <td style="border-bottom: 1px solid #e5e7eb">{{ $product->sku }}</td>
                    <td style="border-bottom: 1px solid #e5e7eb">{{ $product->name }}</td>
                    <td align="right" style="border-bottom: 1px solid #e5e7eb">
                        {{ rtrim(rtrim(number_format((float) $product->reorder_level, 3, ',', ' '), '0'), ',') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="font-size: 11px; color: #6b7280">
        Message automatique envoyé par {{ config('erp.company.name') }}.
    </p>
</body>
</html>
