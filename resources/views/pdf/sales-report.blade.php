<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Informe de Ventas</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #ea580c;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 10px;
        }
        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #ea580c;
            margin: 0;
        }
        .company-info {
            font-size: 10px;
            color: #666;
            margin: 2px 0;
        }
        .report-title {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 15px;
            margin-bottom: 5px;
        }
        .report-period {
            font-size: 12px;
            font-weight: bold;
            color: #555;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            background-color: #f3f4f6;
            padding: 5px;
            border-left: 4px solid #ea580c;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f9fafb;
            font-weight: bold;
            color: #374151;
            font-size: 11px;
        }
        td {
            font-size: 11px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .kpi-container {
            width: 100%;
            margin-bottom: 20px;
        }
        .kpi-box {
            width: 24%;
            display: inline-block;
            box-sizing: border-box;
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            border-radius: 4px;
        }
        .kpi-title {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .kpi-value {
            font-size: 16px;
            font-weight: bold;
            color: #ea580c;
        }
        .footer {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }
    </style>
</head>
<body>

    <div class="header">
        @php
            $logoPath = public_path('images/logo.png');
            $logoData = '';
            if(file_exists($logoPath)) {
                $type = pathinfo($logoPath, PATHINFO_EXTENSION);
                $data = file_get_contents($logoPath);
                $logoData = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        @endphp
        
        @if($logoData)
            <img src="{{ $logoData }}" class="logo" alt="Logo">
        @endif
        
        <h1 class="company-name">FERRETERIA LAS F</h1>
        <p class="company-info">NIT: 7719126 | REGIMEN NO RESPONSABLE DE IVA</p>
        <p class="company-info">CALLE 21 N. 56-74 | TEL: 3142103651 Y 3053198658</p>

        <h2 class="report-title">Informe Estadístico de Ventas</h2>
        <p class="report-period">Período: {{ $startDate }} - {{ $endDate }}</p>
    </div>

    <div class="section-title">Métricas Clave (KPIs)</div>
    <div class="kpi-container">
        <div class="kpi-box">
            <div class="kpi-title">Ventas Totales</div>
            <div class="kpi-value">${{ number_format($kpis['total_sales'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-box">
            <div class="kpi-title">Pedidos Facturados</div>
            <div class="kpi-value">{{ number_format($kpis['orders_count'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-box">
            <div class="kpi-title">Ticket Promedio</div>
            <div class="kpi-value">${{ number_format($kpis['average_ticket'], 0, ',', '.') }}</div>
        </div>
        <div class="kpi-box">
            <div class="kpi-title">Artículos Vendidos</div>
            <div class="kpi-value">{{ number_format($kpis['units_sold'], 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="section-title">Desglose por Medio de Pago</div>
    <table>
        <tr>
            <th>Efectivo</th>
            <th>Tarjeta</th>
            <th>Transferencia</th>
            <th>Crédito / Cartera</th>
        </tr>
        <tr>
            <td class="text-center">${{ number_format($kpis['cash_total'], 0, ',', '.') }}</td>
            <td class="text-center">${{ number_format($kpis['card_total'], 0, ',', '.') }}</td>
            <td class="text-center">${{ number_format($kpis['transfer_total'], 0, ',', '.') }}</td>
            <td class="text-center">${{ number_format($kpis['credit_total'], 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="section-title">Ventas por Caja de Atención</div>
    <table>
        <thead>
            <tr>
                <th>Caja de Atención</th>
                <th class="text-center">Transacciones</th>
                <th class="text-right">Total Recaudado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($registerStats['stats'] as $stat)
                <tr>
                    <td>{{ $stat['register']->name }}
                        @if($registerStats['winner_name'] === $stat['register']->name)
                            <strong>(Mayor Venta)</strong>
                        @endif
                    </td>
                    <td class="text-center">{{ $stat['orders'] }}</td>
                    <td class="text-right">${{ number_format($stat['total'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Top 10 Productos Más Vendidos</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Código (SKU)</th>
                <th>Producto</th>
                <th class="text-center">Unidades</th>
                <th class="text-right">Total Facturado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($topProducts['items'] as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->sku }}</td>
                    <td>{{ $item->name }}</td>
                    <td class="text-center">{{ number_format($item->total_qty, 0, ',', '.') }}</td>
                    <td class="text-right">${{ number_format($item->total_amount, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No hay datos de productos vendidos en este período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generado por Sistema POS Ferretería Las F el {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
    </div>

</body>
</html>
