<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankLineController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SalesOrderController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('ping', fn () => response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]));

    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        /*
        |--------------------------------------------------------------------
        | Session / profile
        |--------------------------------------------------------------------
        */
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::patch('auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        Route::get('dashboard', [DashboardController::class, 'index'])
            ->middleware('permission:dashboard.view');

        /*
        |--------------------------------------------------------------------
        | Clients
        |--------------------------------------------------------------------
        */
        Route::prefix('clients')->group(function () {
            Route::get('/', [ClientController::class, 'index'])->middleware('permission:clients.view');
            Route::post('/', [ClientController::class, 'store'])->middleware('permission:clients.create');
            Route::get('{client}', [ClientController::class, 'show'])->middleware('permission:clients.view');
            Route::match(['put', 'patch'], '{client}', [ClientController::class, 'update'])->middleware('permission:clients.update');
            Route::delete('{client}', [ClientController::class, 'destroy'])->middleware('permission:clients.delete');
        });

        /*
        |--------------------------------------------------------------------
        | Suppliers
        |--------------------------------------------------------------------
        */
        Route::prefix('suppliers')->group(function () {
            Route::get('/', [SupplierController::class, 'index'])->middleware('permission:suppliers.view');
            Route::post('/', [SupplierController::class, 'store'])->middleware('permission:suppliers.create');
            Route::get('{supplier}', [SupplierController::class, 'show'])->middleware('permission:suppliers.view');
            Route::match(['put', 'patch'], '{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.update');
            Route::delete('{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:suppliers.delete');
        });

        /*
        |--------------------------------------------------------------------
        | Catalogue
        |--------------------------------------------------------------------
        */
        Route::prefix('categories')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->middleware('permission:categories.view');
            Route::post('/', [CategoryController::class, 'store'])->middleware('permission:categories.create');
            Route::get('{category}', [CategoryController::class, 'show'])->middleware('permission:categories.view');
            Route::match(['put', 'patch'], '{category}', [CategoryController::class, 'update'])->middleware('permission:categories.update');
            Route::delete('{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete');
        });

        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->middleware('permission:products.view');
            Route::post('/', [ProductController::class, 'store'])->middleware('permission:products.create');
            Route::get('{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
            Route::match(['put', 'patch'], '{product}', [ProductController::class, 'update'])->middleware('permission:products.update');
            Route::delete('{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');
        });

        Route::prefix('warehouses')->group(function () {
            Route::get('/', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view');
            Route::post('/', [WarehouseController::class, 'store'])->middleware('permission:warehouses.create');
            Route::get('{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:warehouses.view');
            Route::match(['put', 'patch'], '{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:warehouses.update');
            Route::delete('{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:warehouses.delete');
        });

        /*
        |--------------------------------------------------------------------
        | Stock
        |--------------------------------------------------------------------
        */
        Route::prefix('stock')->group(function () {
            Route::get('/', [StockController::class, 'index'])->middleware('permission:stock.view');
            Route::get('movements', [StockController::class, 'movements'])->middleware('permission:stock.view');
            Route::post('movements', [StockController::class, 'store'])->middleware('permission:stock.manage');
        });

        /*
        |--------------------------------------------------------------------
        | Purchasing
        |--------------------------------------------------------------------
        */
        Route::prefix('purchase-orders')->group(function () {
            Route::get('/', [PurchaseOrderController::class, 'index'])->middleware('permission:purchase_orders.view');
            Route::post('/', [PurchaseOrderController::class, 'store'])->middleware('permission:purchase_orders.create');
            Route::get('{purchaseOrder}', [PurchaseOrderController::class, 'show'])->middleware('permission:purchase_orders.view');
            Route::match(['put', 'patch'], '{purchaseOrder}', [PurchaseOrderController::class, 'update'])->middleware('permission:purchase_orders.update');
            Route::delete('{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->middleware('permission:purchase_orders.delete');
            Route::post('{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm'])->middleware('permission:purchase_orders.confirm');
            Route::post('{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->middleware('permission:purchase_orders.receive');
            Route::post('{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->middleware('permission:purchase_orders.update');
            Route::post('{purchaseOrder}/payments', [PurchaseOrderController::class, 'storePayment'])->middleware('permission:payments.create');
        });

        /*
        |--------------------------------------------------------------------
        | Sales
        |--------------------------------------------------------------------
        */
        Route::prefix('sales-orders')->group(function () {
            Route::get('/', [SalesOrderController::class, 'index'])->middleware('permission:sales_orders.view');
            Route::post('/', [SalesOrderController::class, 'store'])->middleware('permission:sales_orders.create');
            Route::get('{salesOrder}', [SalesOrderController::class, 'show'])->middleware('permission:sales_orders.view');
            Route::match(['put', 'patch'], '{salesOrder}', [SalesOrderController::class, 'update'])->middleware('permission:sales_orders.update');
            Route::delete('{salesOrder}', [SalesOrderController::class, 'destroy'])->middleware('permission:sales_orders.delete');
            Route::post('{salesOrder}/confirm', [SalesOrderController::class, 'confirm'])->middleware('permission:sales_orders.confirm');
            Route::post('{salesOrder}/deliver', [SalesOrderController::class, 'deliver'])->middleware('permission:sales_orders.deliver');
            Route::post('{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])->middleware('permission:sales_orders.update');
            Route::post('{salesOrder}/invoice', [SalesOrderController::class, 'invoice'])->middleware('permission:invoices.create');
            Route::get('{salesOrder}/delivery-note', [SalesOrderController::class, 'deliveryNote'])->middleware('permission:sales_orders.view');
        });

        /*
        |--------------------------------------------------------------------
        | Quotes
        |--------------------------------------------------------------------
        */
        Route::prefix('quotes')->group(function () {
            Route::get('/', [QuoteController::class, 'index'])->middleware('permission:quotes.view');
            Route::post('/', [QuoteController::class, 'store'])->middleware('permission:quotes.create');
            Route::get('{quote}', [QuoteController::class, 'show'])->middleware('permission:quotes.view');
            Route::match(['put', 'patch'], '{quote}', [QuoteController::class, 'update'])->middleware('permission:quotes.update');
            Route::delete('{quote}', [QuoteController::class, 'destroy'])->middleware('permission:quotes.delete');
            Route::post('{quote}/send', [QuoteController::class, 'send'])->middleware('permission:quotes.send');
            Route::post('{quote}/accept', [QuoteController::class, 'accept'])->middleware('permission:quotes.update');
            Route::post('{quote}/reject', [QuoteController::class, 'reject'])->middleware('permission:quotes.update');
            Route::post('{quote}/convert', [QuoteController::class, 'convert'])->middleware('permission:quotes.convert');
        });

        /*
        |--------------------------------------------------------------------
        | Invoicing & payments
        |--------------------------------------------------------------------
        */
        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])->middleware('permission:invoices.view');
            Route::post('/', [InvoiceController::class, 'store'])->middleware('permission:invoices.create');
            Route::get('{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoices.view');
            Route::match(['put', 'patch'], '{invoice}', [InvoiceController::class, 'update'])->middleware('permission:invoices.update');
            Route::delete('{invoice}', [InvoiceController::class, 'destroy'])->middleware('permission:invoices.delete');
            Route::post('{invoice}/send', [InvoiceController::class, 'send'])->middleware('permission:invoices.send');
            Route::post('{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('permission:invoices.update');
            Route::post('{invoice}/payments', [InvoiceController::class, 'storePayment'])->middleware('permission:payments.create');
            Route::get('{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('permission:invoices.view');
            Route::post('{invoice}/credit-note', [InvoiceController::class, 'creditNote'])->middleware('permission:invoices.create');
            Route::post('{invoice}/email', [InvoiceController::class, 'email'])->middleware('permission:invoices.send');
        });

        Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:payments.view');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->middleware('permission:payments.view');

        /*
        |--------------------------------------------------------------------
        | Bank reconciliation
        |--------------------------------------------------------------------
        */
        Route::prefix('bank-lines')->group(function () {
            Route::get('/', [BankLineController::class, 'index'])->middleware('permission:bank.view');
            Route::post('/', [BankLineController::class, 'store'])->middleware('permission:bank.create');
            Route::get('summary', [BankLineController::class, 'summary'])->middleware('permission:bank.view');
            Route::get('{bankLine}', [BankLineController::class, 'show'])->middleware('permission:bank.view');
            Route::post('{bankLine}/match', [BankLineController::class, 'match'])->middleware('permission:bank.reconcile');
            Route::post('{bankLine}/unmatch', [BankLineController::class, 'unmatch'])->middleware('permission:bank.reconcile');
            Route::get('{bankLine}/suggestions', [BankLineController::class, 'suggestions'])->middleware('permission:bank.view');
        });

        Route::get('activity-logs', [ActivityLogController::class, 'index'])->middleware('permission:audit.view');

        /*
        |--------------------------------------------------------------------
        | Administration
        |--------------------------------------------------------------------
        */
        /*
        |--------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------
        */
        Route::prefix('reports')->middleware('permission:reports.view')->group(function () {
            Route::get('sales', [ReportController::class, 'sales']);
            Route::get('purchases', [ReportController::class, 'purchases']);
            Route::get('stock-valuation', [ReportController::class, 'stockValuation']);
            Route::get('receivables-aging', [ReportController::class, 'receivablesAging']);
        });

        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view');
            Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create');
            Route::get('{user}', [UserController::class, 'show'])->middleware('permission:users.view');
            Route::match(['put', 'patch'], '{user}', [UserController::class, 'update'])->middleware('permission:users.update');
            Route::delete('{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
        });

        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
        Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:roles.view');
        Route::get('permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.view');
    });
});
