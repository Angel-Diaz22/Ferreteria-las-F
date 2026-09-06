<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tirilla de Venta {{ $sale->invoice_number }} - {{ $company['name'] }}</title>
    <style>
        /* ====================================================================
         * ESTILOS TIRILLA TÉRMICA POS (80mm)
         * ====================================================================
         */
        @page {
            margin: 5mm 4mm;
        }

        body {
            font-family: 'Courier New', Courier, monospace, sans-serif;
            font-size: 8.5pt;
            color: #000000;
            line-height: 1.25;
            margin: 0;
            padding: 2px 4px;
            width: 72mm;
            max-width: 72mm;
        }

        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }

        .divider {
            border-top: 1px dashed #000000;
            margin: 5px 0;
        }

        .double-divider {
            border-top: 2px solid #000000;
            margin: 6px 0;
        }

        .header {
            text-align: center;
            margin-bottom: 6px;
        }

        .company-name {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }

        .info-table td {
            padding: 1px 0;
            vertical-align: top;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }

        .items-table th {
            border-bottom: 1px dashed #000;
            padding: 3px 0;
            font-size: 7.5pt;
        }

        .items-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }

        .totals-table td {
            padding: 2px 0;
        }

        .total-amount {
            font-size: 12pt;
            font-weight: bold;
        }

        .footer {
            text-align: center;
            font-size: 7.5pt;
            margin-top: 8px;
        }

        /* Botón de impresión en pantalla (se oculta al imprimir físicamente) */
        .no-print {
            text-align: center;
            margin-bottom: 10px;
            padding: 6px;
            background: #f1f5f9;
            border-radius: 4px;
        }

        @media print {
            .no-print { display: none !important; }
            body { width: 100%; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" style="padding: 6px 14px; background: #0284c7; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">
            🖨️ Imprimir Tirilla
        </button>
        <a href="{{ route('sales.pdf', $sale) }}?download=1" style="margin-left: 8px; font-size: 8.5pt; color: #0284c7;">Descargar PDF</a>
    </div>

    <!-- CABECERA DE LA FERRETERÍA -->
    <div class="header">
        <img src="{{ public_path('images/logo.png') }}" style="width: 52px; height: 52px; margin: 0 auto 4px auto; display: block;" alt="Logo">
        <div class="company-name">{{ $company['name'] }}</div>
        <div>NIT: {{ $company['nit'] }}</div>
        <div>{{ $company['regime'] }}</div>
        <div>{{ $company['address'] }}</div>
        <div>Tel: {{ $company['phone'] }} | {{ $company['whatsapp'] }}</div>
    </div>

    <div class="double-divider"></div>

    <!-- DATOS DEL COMPROBANTE -->
    <div class="text-center font-bold" style="font-size: 9.5pt;">
        COMPROBANTE DE VENTA Y ENTREGA
    </div>
    <div class="text-center font-bold" style="font-size: 11pt; margin-top: 2px;">
        {{ $sale->invoice_number }}
    </div>

    <!-- ESTADO DEL PEDIDO EN EL SISTEMA -->
    <div class="text-center" style="margin-top: 4px;">
        @if($sale->status === 'pending_payment')
            <span style="font-size: 7.5pt; font-weight: bold; border: 1px dashed #000; padding: 2px 6px; display: inline-block;">
                ⏳ PENDIENTE DE PAGO EN CAJA
            </span>
        @elseif($sale->status === 'paid')
            <span style="font-size: 7.5pt; font-weight: bold; border: 1.5px solid #000; padding: 2px 6px; display: inline-block;">
                ✅ PAGADO EN CAJA - LISTO PARA ENTREGA
            </span>
        @elseif(in_array($sale->status, ['delivered', 'completed']))
            <span style="font-size: 7.5pt; font-weight: bold; border: 1.5px solid #000; padding: 2px 6px; display: inline-block;">
                📦 MERCANCÍA ENTREGADA / DESPACHADO
            </span>
        @elseif($sale->status === 'cancelled')
            <span style="font-size: 7.5pt; font-weight: bold; border: 1px dashed #000; padding: 2px 6px; display: inline-block;">
                ❌ PEDIDO ANULADO
            </span>
        @endif
    </div>

    <!-- CUADRO PARA SELLO FÍSICO DE CAJA (ANTI-FRAUDE) -->
    <div style="margin: 8px auto; border: 1.5px dashed #000; border-radius: 4px; padding: 6px 4px; text-align: center; width: 92%;">
        <div style="font-size: 7pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">
            SELLO OFICIAL DE CAJA (GERENCIA)
        </div>
        <div style="height: 38px; display: flex; align-items: center; justify-content: center; font-size: 6.5pt; color: #444;">
            (Espacio exclusivo para el sello físico de tinta "PAGADO")
        </div>
        <div style="font-size: 6pt; color: #333; border-top: 0.5px solid #888; padding-top: 2px;">
            Despacho exige sello físico + validación en sistema
        </div>
    </div>

    <div class="divider"></div>

    <table class="info-table">
        <tr>
            <td style="width: 32%;">Fecha:</td>
            <td class="font-bold">{{ $sale->created_at->format('d/m/Y H:i:s') }}</td>
        </tr>
        <tr>
            <td>Cajero:</td>
            <td>{{ $sale->user?->name ?? 'Caja Mostrador' }}</td>
        </tr>
        <tr>
            <td>Bodega:</td>
            <td>{{ $sale->warehouse?->name ?? 'Bodega Principal' }}</td>
        </tr>
        <tr>
            <td>Cliente:</td>
            <td class="font-bold">{{ $sale->customer?->name ?? 'Cliente Mostrador / Ocasional' }}</td>
        </tr>
        @if($sale->customer)
        <tr>
            <td>Doc/NIT:</td>
            <td>{{ $sale->customer->document_type }} {{ $sale->customer->document }}</td>
        </tr>
        @endif
        <tr>
            <td>Medio Pago:</td>
            <td class="font-bold uppercase">
                @switch($sale->payment_method)
                    @case('cash') Efectivo @break
                    @case('card') Tarjeta / Datáfono @break
                    @case('credit') Crédito / Cartera @break
                    @case('transfer') Transferencia @break
                    @default {{ $sale->payment_method }}
                @endswitch
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- DETALLE DE ARTÍCULOS -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-left" style="width: 50%;">Producto</th>
                <th class="text-center" style="width: 15%;">Cant</th>
                <th class="text-right" style="width: 35%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->items as $item)
                <tr>
                    <td colspan="3" class="font-bold" style="padding-top: 3px;">
                        {{ $item->product?->name ?? 'Producto' }}
                    </td>
                </tr>
                <tr>
                    <td class="text-left" style="color: #444; font-size: 7pt;">
                        SKU: {{ $item->product?->sku }}
                    </td>
                    <td class="text-center">
                        {{ number_format($item->quantity, 0, ',', '.') }} x ${{ number_format($item->unit_price, 0, ',', '.') }}
                    </td>
                    <td class="text-right font-bold">
                        ${{ number_format($item->subtotal, 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <!-- LIQUIDACIÓN DE TOTALES -->
    <!-- Como Ferretería Las F es No Responsable de IVA, NO desglosamos el IVA en la tirilla -->
    <table class="totals-table">
        @if($sale->discount_amount > 0)
        <tr>
            <td class="text-right">Descuento:</td>
            <td class="text-right font-bold" style="width: 40%;">-${{ number_format($sale->discount_amount, 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr class="double-divider">
            <td colspan="2"></td>
        </tr>
        <tr>
            <td class="text-right total-amount">TOTAL:</td>
            <td class="text-right total-amount">${{ number_format($sale->total, 0, ',', '.') }} COP</td>
        </tr>
        @if($sale->payment_method === 'cash')
        <tr>
            <td class="text-right" style="padding-top: 4px;">Recibido:</td>
            <td class="text-right font-bold" style="padding-top: 4px;">${{ number_format($sale->paid_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="text-right font-bold" style="font-size: 9.5pt;">Cambio / Vueltas:</td>
            <td class="text-right font-bold" style="font-size: 9.5pt;">${{ number_format($sale->change_amount, 0, ',', '.') }}</td>
        </tr>
        @endif
    </table>

    <div class="double-divider"></div>

    <!-- TÉRMINOS Y LEYENDA LEGAL -->
    <div class="footer">
        <div><strong>¡GRACIAS POR SU COMPRA!</strong></div>
        <div style="margin: 3px 0;">Revise su mercancía antes de retirarse.</div>
        <div style="margin: 2px 0;">Garantía de 30 días con este recibo.</div>
        <div style="font-size: 6.5pt; color: #555; margin-top: 5px;">
            Documento para control interno y soporte contable.<br>
            No constituye factura electrónica ante la DIAN.
        </div>
    </div>

</body>
</html>
