<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Cotización {{ $quote->quote_number }} - {{ $company['name'] }}</title>
    <style>
        /* ====================================================================
         * ESTILOS CSS OPTIMIZADOS PARA DOMPDF
         * ====================================================================
         * Regla de oro para DomPDF:
         * 1. Usar siempre tablas con anchos porcentuales fijos (table-layout: fixed)
         *    para asegurar alineación milimétrica en PDF.
         * 2. Evitar margin-left: auto y display: inline-block que causan
         *    descuadres en distintas versiones de DomPDF.
         */
        @page {
            margin: 28pt 32pt 35pt 32pt;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            color: #1e293b;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }

        /* Clases utilitarias */
        .w-100 { width: 100%; border-collapse: collapse; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .text-muted { color: #64748b; font-size: 8.5pt; }

        /* Cabecera Principal */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .header-table td {
            vertical-align: top;
        }

        .company-title {
            font-size: 15pt;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 3px 0;
            letter-spacing: -0.3px;
        }

        .quote-badge-table {
            width: 220px;
            border-collapse: collapse;
            border: 2px solid #0284c7;
            background-color: #f0f9ff;
            border-radius: 6px;
            margin-left: auto;
        }

        .quote-badge-table td {
            padding: 8px 10px;
            text-align: center;
        }

        .quote-badge-title {
            font-size: 9pt;
            font-weight: bold;
            color: #0369a1;
            letter-spacing: 0.8px;
        }

        .quote-number {
            font-size: 14pt;
            font-weight: bold;
            color: #0f172a;
            margin: 2px 0;
        }

        .quote-status {
            font-size: 8pt;
            color: #475569;
        }

        /* Bloque de Información: Cliente y Condiciones (Dos Columnas Alineadas) */
        .info-container {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .info-container > tbody > tr > td {
            vertical-align: top;
        }

        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 9px 12px;
        }

        .info-card-header {
            font-size: 8.5pt;
            font-weight: bold;
            color: #0369a1;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }

        .card-table {
            width: 100%;
            border-collapse: collapse;
        }

        .card-table td {
            padding: 2.5px 0;
            vertical-align: top;
            font-size: 9pt;
        }

        .card-label {
            width: 95px;
            color: #64748b;
            font-weight: normal;
        }

        .card-value {
            color: #0f172a;
        }

        /* Tabla de Artículos Cotizados */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            table-layout: fixed;
        }

        .items-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 7px 8px;
            border: 1px solid #0f172a;
        }

        .items-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 8.5pt;
            vertical-align: middle;
        }

        .items-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* Cuadro de Totales y Notas */
        .bottom-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .bottom-section td {
            vertical-align: top;
        }

        .notes-box {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 4px;
            padding: 8px 10px;
            font-size: 8.5pt;
            color: #334155;
            min-height: 50px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 4px 6px;
            font-size: 9pt;
        }

        .summary-total-row {
            background-color: #0284c7;
            color: #ffffff;
            font-weight: bold;
            border-radius: 4px;
        }

        .summary-total-row td {
            padding: 7px 8px;
            color: #ffffff;
            font-size: 10pt;
        }

        /* Cláusulas Comerciales y Pie de Página */
        .terms-box {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            border-left: 3px solid #f59e0b;
            border-radius: 4px;
            padding: 8px 10px;
            font-size: 8pt;
            color: #92400e;
            line-height: 1.35;
            margin-bottom: 16px;
        }

        .footer {
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            text-align: center;
            font-size: 7.5pt;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <!-- ================================================================== -->
    <!-- 1. CABECERA: MEMBRETE DE LA FERRETERÍA Y CAJA DE LA COTIZACIÓN      -->
    <!-- ================================================================== -->
    <table class="header-table">
        <tr>
            <td style="width: 14%; vertical-align: top;">
                <img src="{{ public_path('images/logo.png') }}" style="width: 72px; height: 72px; display: block;" alt="Logo">
            </td>
            <td style="width: 48%;">
                <div class="company-title">{{ $company['name'] }}</div>
                <div class="font-bold text-muted">NIT: {{ $company['nit'] }} | {{ $company['regime'] }}</div>
                <div class="text-muted">{{ $company['address'] }} - {{ $company['city'] }}</div>
                <div class="text-muted">Tel: {{ $company['phone'] }} | WhatsApp: {{ $company['whatsapp'] }}</div>
                <div class="text-muted">Email: {{ $company['email'] }}</div>
            </td>
            <td style="width: 38%; text-align: right;">
                <table class="quote-badge-table">
                    <tr>
                        <td>
                            <div class="quote-badge-title">COTIZACIÓN COMERCIAL</div>
                            <div class="quote-number">{{ $quote->quote_number }}</div>
                            <div class="quote-status">
                                Estado: <strong class="uppercase">{{ $quote->status }}</strong>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ================================================================== -->
    <!-- 2. DATOS DEL CLIENTE Y CONDICIONES (COLUMNAS ALINEADAS)            -->
    <!-- ================================================================== -->
    <table class="info-container">
        <tr>
            <!-- Tarjeta Cliente (Izquierda) -->
            <td style="width: 50%; padding-right: 6px;">
                <div class="info-card">
                    <div class="info-card-header">Datos del Cliente</div>
                    <table class="card-table">
                        <tr>
                            <td class="card-label">Cliente:</td>
                            <td class="card-value font-bold">{{ $quote->customer?->name ?? 'Cliente Mostrador / Ocasional' }}</td>
                        </tr>
                        <tr>
                            <td class="card-label">Documento/NIT:</td>
                            <td class="card-value">{{ $quote->customer?->document_type ?? 'CC' }} {{ $quote->customer?->document ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="card-label">Teléfono:</td>
                            <td class="card-value">{{ $quote->customer?->phone ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="card-label">Dirección:</td>
                            <td class="card-value">{{ $quote->customer?->address ?? 'Local' }} - {{ $quote->customer?->city ?? 'Bogotá' }}</td>
                        </tr>
                        @if($quote->customer?->priceList)
                        <tr>
                            <td class="card-label">Tarifa cliente:</td>
                            <td class="card-value" style="color: #0284c7; font-weight: bold;">{{ $quote->customer->priceList->name }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </td>

            <!-- Tarjeta Oferta / Condiciones (Derecha) -->
            <td style="width: 50%; padding-left: 6px;">
                <div class="info-card">
                    <div class="info-card-header">Condiciones de la Oferta</div>
                    <table class="card-table">
                        <tr>
                            <td class="card-label">Fecha Emisión:</td>
                            <td class="card-value">{{ $quote->created_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td class="card-label">Válida Hasta:</td>
                            <td class="card-value font-bold" style="color: #b91c1c;">{{ $quote->valid_until?->format('d/m/Y') ?? '15 días' }}</td>
                        </tr>
                        <tr>
                            <td class="card-label">Asesor Ventas:</td>
                            <td class="card-value">{{ $quote->user?->name ?? 'Vendedor Mostrador' }}</td>
                        </tr>
                        <tr>
                            <td class="card-label">Forma de Pago:</td>
                            <td class="card-value">Contado / Crédito según cupo</td>
                        </tr>
                        <tr>
                            <td class="card-label">Moneda:</td>
                            <td class="card-value">Pesos Colombianos (COP)</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- ================================================================== -->
    <!-- 3. TABLA DE ARTÍCULOS COTIZADOS (ANCHOS EXACTOS Y ALINEADOS)       -->
    <!-- ================================================================== -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 14%; text-align: left;">SKU</th>
                <th style="width: 44%; text-align: left;">Descripción del Producto</th>
                <th style="width: 10%; text-align: center;">Cant.</th>
                <th style="width: 16%; text-align: right;">Precio Unit.</th>
                <th style="width: 16%; text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse($quote->items as $item)
                <tr>
                    <td class="text-left font-bold" style="color: #475569;">{{ $item->product?->sku ?? 'N/A' }}</td>
                    <td class="text-left">
                        <span class="font-bold">{{ $item->product?->name ?? 'Producto no especificado' }}</span>
                        @if($item->product?->brand)
                            <span class="text-muted">({{ $item->product->brand->name }})</span>
                        @endif
                    </td>
                    <td class="text-center font-bold">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($item->unit_price, 2, ',', '.') }}</td>
                    <td class="text-right font-bold">${{ number_format($item->subtotal, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="padding: 16px; color: #94a3b8;">
                        No se han registrado productos en esta cotización.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- ================================================================== -->
    <!-- 4. TOTALES Y OBSERVACIONES                                         -->
    <!-- ================================================================== -->
    <table class="bottom-section">
        <tr>
            <!-- Observaciones adicionales a la izquierda -->
            <td style="width: 54%; padding-right: 18px;">
                @if(!empty($quote->notes))
                    <div class="notes-box">
                        <strong style="color: #475569; font-size: 8pt; text-transform: uppercase;">Notas y Observaciones Comerciales:</strong>
                        <div style="margin-top: 3px; font-size: 8.5pt; color: #1e293b; white-space: pre-line;">{{ $quote->notes }}</div>
                    </div>
                @endif
            </td>

            <!-- Resumen numérico alineado a la derecha -->
            <td style="width: 46%;">
                <table class="summary-table">
                    <tr>
                        <td class="text-right text-muted" style="width: 58%;">{{ (float) $quote->tax_amount > 0 ? 'Subtotal antes de IVA:' : 'Subtotal:' }}</td>
                        <td class="text-right font-bold" style="width: 42%;">${{ number_format($quote->subtotal, 2, ',', '.') }}</td>
                    </tr>
                    @if((float) $quote->tax_amount > 0)
                    <tr>
                        <td class="text-right text-muted">IVA Estimado ({{ number_format(\App\Models\SystemSetting::getIvaRate(), 0) }}%):</td>
                        <td class="text-right font-bold">${{ number_format($quote->tax_amount, 2, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr class="summary-total-row">
                        <td class="text-right" style="border-top: 1px solid #0284c7;">TOTAL COTIZADO:</td>
                        <td class="text-right" style="border-top: 1px solid #0284c7;">${{ number_format($quote->total, 2, ',', '.') }} COP</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ================================================================== -->
    <!-- 5. TÉRMINOS Y CONDICIONES (HABEAS DATA Y VALIDEZ)                  -->
    <!-- ================================================================== -->
    <div class="terms-box">
        <strong>TÉRMINOS Y CONDICIONES COMERCIALES:</strong><br>
        • Los precios cotizados están garantizados hasta la fecha de validez estipulada.<br>
        • La entrega y despacho quedan sujetos a disponibilidad de inventario físico en bodega al confirmar el pedido.<br>
        • En cumplimiento de la <strong>Ley 1581 de 2012 de Protección de Datos Personales</strong>, sus datos son tratados de manera confidencial para fines exclusivos de facturación y despacho comercial.
    </div>

    <!-- 6. PIE DE PÁGINA -->
    <div class="footer">
        {{ $company['name'] }} • Sistema Integrado POS & Gestión de Inventarios • Página generada automáticamente
    </div>

</body>
</html>
