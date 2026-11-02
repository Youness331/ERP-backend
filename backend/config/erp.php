<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Company identity (printed on documents)
    |--------------------------------------------------------------------------
    */
    'company' => [
        'name' => env('ERP_COMPANY_NAME', 'Atlas Operations SARL'),
        'address' => env('ERP_COMPANY_ADDRESS', '12, Boulevard Zerktouni'),
        'city' => env('ERP_COMPANY_CITY', 'Casablanca'),
        'country' => env('ERP_COMPANY_COUNTRY', 'Maroc'),
        'phone' => env('ERP_COMPANY_PHONE', '+212 5 22 00 00 00'),
        'email' => env('ERP_COMPANY_EMAIL', 'contact@atlas-operations.ma'),
        'ice' => env('ERP_COMPANY_ICE', '001234567000089'),
        'tax_id' => env('ERP_COMPANY_TAX_ID', '12345678'),
        'rc' => env('ERP_COMPANY_RC', 'RC 123456'),
        'patente' => env('ERP_COMPANY_PATENTE', 'PAT 654321'),
    ],

    'currency' => [
        'code' => env('ERP_CURRENCY', 'MAD'),
        'symbol' => env('ERP_CURRENCY_SYMBOL', 'DH'),
        'decimals' => 2,
    ],

    'tax' => [
        // Default VAT rate applied to new products and invoice lines.
        'default_rate' => (float) env('ERP_DEFAULT_TAX_RATE', 20),
    ],

    'invoice' => [
        'due_days' => (int) env('ERP_INVOICE_DUE_DAYS', 30),
    ],
];
