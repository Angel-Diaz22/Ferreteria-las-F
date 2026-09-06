<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ============================================================================
 * CONTROLADOR: QuotePdfController (Generación de Cotizaciones en PDF)
 * ============================================================================
 * ¿CÓMO FUNCIONA LA GENERACIÓN DE PDF EN LARAVEL?
 * 1. Laravel recibe la petición HTTP GET /admin/quotes/{quote}/pdf.
 * 2. Eloquent carga la cotización con sus relaciones (cliente, items, productos).
 * 3. El motor de plantillas Blade (`pdf.quote`) compila el HTML y CSS plano.
 * 4. El paquete `dompdf` toma ese HTML y lo convierte en un archivo binario PDF.
 * 5. Enviamos la respuesta HTTP con las cabeceras `Content-Type: application/pdf`.
 *    El navegador del usuario puede previsualizarlo o descargarlo directamente.
 */
class QuotePdfController extends Controller
{
    /**
     * Descarga o visualiza en el navegador la cotización comercial en PDF.
     */
    public function download(Request $request, Quote $quote): Response
    {
        // Cargamos todas las relaciones necesarias para evitar consultas N+1 en la vista:
        $quote->load([
            'customer.priceList',
            'user',
            'items.product.category',
            'items.product.brand',
        ]);

        // Datos institucionales reales de la Ferretería:
        $company = [
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

        // Compilamos la vista Blade con dompdf:
        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $quote,
            'company' => $company,
        ]);

        // Configuramos tamaño de papel Carta (Letter) y orientación vertical:
        $pdf->setPaper('letter', 'portrait');

        $filename = "Cotizacion_{$quote->quote_number}.pdf";

        // Si la URL tiene el parámetro ?download=1 forzamos la descarga directa,
        // de lo contrario previsualizamos en la pestaña del navegador (stream):
        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}
