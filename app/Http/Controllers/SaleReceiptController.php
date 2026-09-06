<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * ============================================================================
 * CONTROLADOR: SaleReceiptController (Tirilla de Venta Térmica POS)
 * ============================================================================
 * ¿CÓMO FUNCIONA LA IMPRESIÓN EN UN PUNTO DE VENTA?
 * Las impresoras térmicas de punto de venta (Epson, Bixolon, Xprinter de 80mm)
 * requieren formatos estrechos (80mm / aprox. 226pt de ancho).
 *
 * Este controlador ofrece dos modalidades:
 * 1. `print()`: Vista HTML compacta con disparador automático `window.print()`
 *    ideal para imprimir en la impresora térmica conectada al computador en un clic.
 * 2. `pdf()`: Archivo PDF renderizado con dompdf en dimensiones exactas de rollo térmico.
 */
class SaleReceiptController extends Controller
{
    /**
     * Muestra la vista térmica HTML lista para imprimir con window.print().
     */
    public function print(Sale $sale): View
    {
        if (auth()->user()?->roles()->exists() && ! auth()->user()?->can('sales.view') && auth()->id() !== $sale->user_id) {
            abort(403, 'No tienes permiso para ver recibos de venta.');
        }

        $sale->load(['customer', 'user', 'warehouse', 'items.product']);

        $company = $this->getCompanyInfo();

        return view('pdf.receipt', compact('sale', 'company'));
    }

    /**
     * Genera la tirilla en formato binario PDF ajustado a papel continuo de 80mm.
     */
    public function pdf(Request $request, Sale $sale): Response
    {
        if (auth()->user()?->roles()->exists() && ! auth()->user()?->can('sales.view') && auth()->id() !== $sale->user_id) {
            abort(403, 'No tienes permiso para descargar recibos de venta.');
        }

        $sale->load(['customer', 'user', 'warehouse', 'items.product']);

        $company = $this->getCompanyInfo();

        // 80mm = ~226.77 puntos. Calculamos una altura proporcional según la cantidad de productos:
        $calculatedHeight = 350 + (count($sale->items) * 35);

        $pdf = Pdf::loadView('pdf.receipt', compact('sale', 'company'))
            ->setPaper([0, 0, 226.77, $calculatedHeight], 'portrait');

        $filename = "Tirilla_{$sale->invoice_number}.pdf";

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Retorna los datos institucionales reales de la empresa.
     * Estos datos aparecen en todas las tirillas y comprobantes impresos.
     *
     * @return array<string, string>
     */
    protected function getCompanyInfo(): array
    {
        return [
            'name' => 'FERRETERÍA LAS F',
            'nit' => '7719126',
            'regime' => 'No Responsable de IVA',
            'address' => 'Calle 21 N. 56-74',
            'city' => 'Colombia',
            'phone' => '314 210 3651',
            'whatsapp' => '305 319 8658',
            'email' => '',
            'web' => '',
        ];
    }
}
