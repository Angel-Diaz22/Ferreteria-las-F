<?php

use App\Http\Controllers\QuotePdfController;
use App\Http\Controllers\SaleReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Rutas protegidas por autenticación y limitadas para prevenir abusos de recursos (DoS por renderizado PDF):
Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    // Cotizaciones en PDF
    Route::get('/admin/quotes/{quote}/pdf', [QuotePdfController::class, 'download'])->name('quotes.pdf');

    // Comprobantes de Venta y Tirillas Térmicas POS
    Route::get('/admin/sales/{sale}/receipt', [SaleReceiptController::class, 'print'])->name('sales.receipt');
    Route::get('/admin/sales/{sale}/pdf', [SaleReceiptController::class, 'pdf'])->name('sales.pdf');
});
