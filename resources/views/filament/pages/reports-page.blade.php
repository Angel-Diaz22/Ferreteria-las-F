<x-filament-panels::page>
    {{-- Estilos dedicados para el Módulo de Informes --}}
    <style>
        :root {
            --flf-orange: #ea580c;
            --flf-orange-hover: #c2410c;
            --flf-orange-light: #fff7ed;
            --flf-green: #16a34a;
            --flf-green-light: #f0fdf4;
            --flf-dark: #0f172a;
            --flf-border: #e2e8f0;
        }

        .reports-card {
            background-color: #ffffff;
            border: 1px solid var(--flf-border);
            border-radius: 1rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .reports-kpi-card {
            background-color: #ffffff;
            border: 1.5px solid var(--flf-border);
            border-radius: 1rem;
            padding: 1.25rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .reports-kpi-card:hover {
            border-color: #fdba74;
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.08);
        }

        .reports-badge-orange {
            background-color: #fff7ed;
            color: #ea580c;
            border: 1px solid #fed7aa;
            font-weight: 700;
            padding: 0.25rem 0.6rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
        }

        .reports-badge-green {
            background-color: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
            font-weight: 700;
            padding: 0.25rem 0.6rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
        }

        /* Botones de Rango Rápido (Preset) */
        .reports-btn-preset {
            background-color: #f8fafc !important;
            color: #334155 !important;
            border: 1px solid #cbd5e1 !important;
            padding: 0.4rem 0.85rem !important;
            border-radius: 0.5rem !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            transition: all 0.15s ease !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04) !important;
        }

        .reports-btn-preset:hover {
            background-color: #fff7ed !important;
            border-color: #fdba74 !important;
            color: #ea580c !important;
        }

        /* Botón SELECCIONADO / ACTIVO: Naranja corporativo visible y nítido */
        .reports-btn-preset.active,
        .reports-btn-preset.active:focus,
        .reports-btn-preset.active:active,
        .reports-btn-preset.active:hover {
            background-color: #ea580c !important;
            color: #ffffff !important;
            border-color: #ea580c !important;
            box-shadow: 0 2px 6px -1px rgba(234, 88, 12, 0.45) !important;
            font-weight: 800 !important;
        }

        /* Botón Descargar Informe PDF */
        .reports-btn-download {
            background-color: #ea580c !important;
            color: #ffffff !important;
            border: 1px solid #c2410c !important;
            padding: 0.45rem 1rem !important;
            border-radius: 0.6rem !important;
            font-size: 0.75rem !important;
            font-weight: 800 !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 0.4rem !important;
            box-shadow: 0 2px 4px rgba(234, 88, 12, 0.3) !important;
            transition: all 0.15s ease !important;
        }

        .reports-btn-download:hover {
            background-color: #c2410c !important;
            border-color: #9a3412 !important;
            color: #ffffff !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 8px rgba(234, 88, 12, 0.35) !important;
        }

        /* Mantiene el fondo y color intactos al hacer clic, seleccionarse o recibir foco */
        .reports-btn-download:focus,
        .reports-btn-download:active,
        .reports-btn-download:focus-visible {
            background-color: #c2410c !important;
            color: #ffffff !important;
            border-color: #9a3412 !important;
            outline: 2px solid #fdba74 !important;
            outline-offset: 1px !important;
        }

        .reports-btn-download:disabled {
            opacity: 0.75 !important;
            cursor: wait !important;
        }

        .reports-select {
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            border-radius: 0.5rem !important;
            border: 1px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            color: #1e293b !important;
            padding: 0.4rem 2rem 0.4rem 0.75rem !important;
        }

        .reports-select:focus {
            border-color: #ea580c !important;
            outline: none !important;
            box-shadow: 0 0 0 2px rgba(234, 88, 12, 0.2) !important;
        }

        .reports-input-date {
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            border-radius: 0.5rem !important;
            border: 1px solid #cbd5e1 !important;
            background-color: #ffffff !important;
            color: #1e293b !important;
            padding: 0.35rem 0.6rem !important;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04) !important;
        }

        .reports-input-date:focus {
            border-color: #ea580c !important;
            outline: none !important;
            box-shadow: 0 0 0 2px rgba(234, 88, 12, 0.2) !important;
        }
    </style>

    <div class="space-y-6" x-data="reportsModule()">

        {{-- ============================================================= --}}
        {{-- PANEL DE FILTROS POR FECHA Y CAJA                             --}}
        {{-- ============================================================= --}}
        <div class="reports-card p-4 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-calendar class="w-5 h-5 text-orange-600" />
                    <span class="text-sm font-black text-gray-900 uppercase tracking-wide">Filtros de Período y Caja:</span>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    {{-- Selector de Caja de Atención --}}
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-600">Caja:</span>
                        <select
                            wire:model.live="selectedRegisterId"
                            class="reports-select"
                        >
                            <option value="">Todas las Cajas de Atención</option>
                            @foreach($this->registersList as $reg)
                                <option value="{{ $reg->id }}">{{ $reg->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Botón Descargar Informe PDF --}}
                    <button
                        wire:click="downloadPdf"
                        wire:loading.attr="disabled"
                        type="button"
                        class="reports-btn-download"
                        title="Descargar informe formal en PDF"
                    >
                        <span wire:loading.remove wire:target="downloadPdf" class="inline-flex items-center gap-1.5 text-white">
                            <x-heroicon-o-document-arrow-down class="w-4 h-4 text-white" />
                            Descargar Informe PDF
                        </span>
                        <span wire:loading wire:target="downloadPdf" class="inline-flex items-center gap-1.5 text-white">
                            <svg class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Generando PDF...
                        </span>
                    </button>
                </div>
            </div>

            {{-- Botones de Rango Rápido y Fechas Personalizadas --}}
            <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        wire:click="applyPreset('today')"
                        type="button"
                        class="reports-btn-preset {{ $datePreset === 'today' ? 'active' : '' }}"
                    >
                        Hoy
                    </button>
                    <button
                        wire:click="applyPreset('yesterday')"
                        type="button"
                        class="reports-btn-preset {{ $datePreset === 'yesterday' ? 'active' : '' }}"
                    >
                        Ayer
                    </button>
                    <button
                        wire:click="applyPreset('last_7_days')"
                        type="button"
                        class="reports-btn-preset {{ $datePreset === 'last_7_days' ? 'active' : '' }}"
                    >
                        Últimos 7 Días
                    </button>
                    <button
                        wire:click="applyPreset('this_month')"
                        type="button"
                        class="reports-btn-preset {{ $datePreset === 'this_month' ? 'active' : '' }}"
                    >
                        Este Mes
                    </button>
                    <button
                        wire:click="applyPreset('last_month')"
                        type="button"
                        class="reports-btn-preset {{ $datePreset === 'last_month' ? 'active' : '' }}"
                    >
                        Mes Anterior
                    </button>
                </div>

                {{-- Selectores de Fecha Personalizada --}}
                <div class="flex items-center gap-1.5 text-xs font-bold text-gray-600">
                    <span>Desde:</span>
                    <input
                        type="date"
                        wire:model.live="startDate"
                        class="reports-input-date"
                    />
                    <span>Hasta:</span>
                    <input
                        type="date"
                        wire:model.live="endDate"
                        class="reports-input-date"
                    />
                </div>
            </div>
        </div>

        {{-- ============================================================= --}}
        {{-- TARJETAS DE MÉTRICAS CLAVE (KPIS)                             --}}
        {{-- ============================================================= --}}
        @php $kpis = $this->kpis; @endphp
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- KPI 1: Ventas Totales --}}
            <div class="reports-kpi-card border-l-4 border-l-orange-500">
                <div class="flex items-center justify-between text-gray-500 mb-1">
                    <span class="text-xs font-bold uppercase tracking-wider">Ventas Totales</span>
                    <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center text-orange-600">
                        <x-heroicon-o-banknotes class="w-5 h-5" />
                    </div>
                </div>
                <div class="my-1">
                    <span class="text-2xl font-black text-gray-900">
                        ${{ number_format($kpis['total_sales'], 0, ',', '.') }}
                    </span>
                    <span class="text-xs font-bold text-gray-500"> COP</span>
                </div>
                <div class="text-[11px] text-gray-500 flex items-center justify-between pt-2 border-t border-gray-100">
                    <span>Facturación del período</span>
                    <span class="reports-badge-green font-black">Activo</span>
                </div>
            </div>

            {{-- KPI 2: Cantidad de Pedidos / Transacciones --}}
            <div class="reports-kpi-card border-l-4 border-l-blue-500">
                <div class="flex items-center justify-between text-gray-500 mb-1">
                    <span class="text-xs font-bold uppercase tracking-wider">Pedidos Facturados</span>
                    <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600">
                        <x-heroicon-o-document-text class="w-5 h-5" />
                    </div>
                </div>
                <div class="my-1">
                    <span class="text-2xl font-black text-gray-900">
                        {{ number_format($kpis['orders_count']) }}
                    </span>
                    <span class="text-xs font-bold text-gray-500"> facturas</span>
                </div>
                <div class="text-[11px] text-gray-500 flex items-center justify-between pt-2 border-t border-gray-100">
                    <span>Transacciones atendidas</span>
                    <span class="reports-badge-orange font-black">{{ $this->selectedRegisterId ? 'Caja Seleccionada' : 'Global' }}</span>
                </div>
            </div>

            {{-- KPI 3: Ticket Promedio --}}
            <div class="reports-kpi-card border-l-4 border-l-green-500">
                <div class="flex items-center justify-between text-gray-500 mb-1">
                    <span class="text-xs font-bold uppercase tracking-wider">Ticket Promedio</span>
                    <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center text-green-600">
                        <x-heroicon-o-calculator class="w-5 h-5" />
                    </div>
                </div>
                <div class="my-1">
                    <span class="text-2xl font-black text-gray-900">
                        ${{ number_format($kpis['average_ticket'], 0, ',', '.') }}
                    </span>
                    <span class="text-xs font-bold text-gray-500"> COP</span>
                </div>
                <div class="text-[11px] text-gray-500 flex items-center justify-between pt-2 border-t border-gray-100">
                    <span>Promedio por cliente</span>
                    <span class="text-green-700 font-bold">Cobrado</span>
                </div>
            </div>

            {{-- KPI 4: Unidades Vendidas --}}
            <div class="reports-kpi-card border-l-4 border-l-purple-500">
                <div class="flex items-center justify-between text-gray-500 mb-1">
                    <span class="text-xs font-bold uppercase tracking-wider">Artículos Despachados</span>
                    <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center text-purple-600">
                        <x-heroicon-o-archive-box class="w-5 h-5" />
                    </div>
                </div>
                <div class="my-1">
                    <span class="text-2xl font-black text-gray-900">
                        {{ number_format($kpis['units_sold'], 0) }}
                    </span>
                    <span class="text-xs font-bold text-gray-500"> unidades</span>
                </div>
                <div class="text-[11px] text-gray-500 flex items-center justify-between pt-2 border-t border-gray-100">
                    <span>Volumen físico</span>
                    <span class="text-purple-700 font-bold">Bodega Principal</span>
                </div>
            </div>
        </div>

        {{-- Desglose por Medio de Pago --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 p-3 bg-gray-50 rounded-xl border border-gray-200 text-xs">
            <div class="flex items-center justify-between p-2.5 bg-white rounded-lg border border-gray-200">
                <span class="font-bold text-gray-600 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                    Efectivo:
                </span>
                <span class="font-black text-gray-900">${{ number_format($kpis['cash_total'], 0, ',', '.') }}</span>
            </div>
            <div class="flex items-center justify-between p-2.5 bg-white rounded-lg border border-gray-200">
                <span class="font-bold text-gray-600 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    Tarjeta:
                </span>
                <span class="font-black text-gray-900">${{ number_format($kpis['card_total'], 0, ',', '.') }}</span>
            </div>
            <div class="flex items-center justify-between p-2.5 bg-white rounded-lg border border-gray-200">
                <span class="font-bold text-gray-600 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
                    Transferencia:
                </span>
                <span class="font-black text-gray-900">${{ number_format($kpis['transfer_total'], 0, ',', '.') }}</span>
            </div>
            <div class="flex items-center justify-between p-2.5 bg-white rounded-lg border border-gray-200">
                <span class="font-bold text-gray-600 flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span>
                    Crédito / Cartera:
                </span>
                <span class="font-black text-gray-900">${{ number_format($kpis['credit_total'], 0, ',', '.') }}</span>
            </div>
        </div>

        {{-- ============================================================= --}}
        {{-- HISTOGRAMA DIARIO DE VENTAS (CHART.JS)                         --}}
        {{-- ============================================================= --}}
        <div class="reports-card p-5 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-gray-100">
                <div>
                    <h3 class="text-sm font-black text-gray-900 flex items-center gap-2">
                        <x-heroicon-o-chart-bar class="w-5 h-5 text-orange-600" />
                        Histograma Diario de Ventas
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Monto diario facturado en el rango de fechas seleccionado (en pesos colombianos).
                    </p>
                </div>
                <span class="reports-badge-orange">Evolución Diaria</span>
            </div>

            <div class="relative w-full" style="height: 280px;" wire:ignore>
                <canvas id="dailySalesChart"></canvas>
            </div>
        </div>

        {{-- ============================================================= --}}
        {{-- 2 COLUMNAS: TOP 10 PRODUCTOS Y RECAUDACIÓN POR CAJA           --}}
        {{-- ============================================================= --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

            {{-- Columna Izquierda (7/12): Top 10 Productos Más Vendidos --}}
            <div class="reports-card p-5 space-y-4 lg:col-span-7">
                <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                    <div>
                        <h3 class="text-sm font-black text-gray-900 flex items-center gap-2">
                            <x-heroicon-o-fire class="w-5 h-5 text-orange-600" />
                            Top 10 Productos Más Vendidos
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Artículos líderes por recaudación y unidades despachadas.
                        </p>
                    </div>
                    <span class="reports-badge-orange">Líderes</span>
                </div>

                <div class="relative w-full" style="height: 320px;" wire:ignore>
                    <canvas id="topProductsChart"></canvas>
                </div>

            </div>

            {{-- Columna Derecha (5/12): Ventas por Caja de Atención --}}
            @php $registerStats = $this->registerStats; @endphp
            <div class="reports-card p-5 space-y-4 lg:col-span-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                        <div>
                            <h3 class="text-sm font-black text-gray-900 flex items-center gap-2">
                                <x-heroicon-o-computer-desktop class="w-5 h-5 text-green-600" />
                                Ventas por Caja de Atención
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Comparativa de recaudación entre cajas.
                            </p>
                        </div>
                        <span class="reports-badge-green">Cajas</span>
                    </div>

                    {{-- Insignia de Caja Ganadora --}}
                    @if($registerStats['winner_name'])
                        <div class="mt-3 p-3.5 rounded-xl border flex items-center gap-3"
                            style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-color: #86efac;">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-2xl shrink-0" style="background-color: #16a34a; color: #ffffff;">
                                <x-heroicon-s-trophy class="w-6 h-6" />
                            </div>
                            <div>
                                <span class="text-[10px] uppercase font-black text-green-800 tracking-wider">Caja con Mayor Venta:</span>
                                <h4 class="text-sm font-black text-green-900">{{ $registerStats['winner_name'] }}</h4>
                                <p class="text-xs font-bold text-green-800">
                                    ${{ number_format($registerStats['winner_total'], 0, ',', '.') }} COP recaudados
                                </p>
                            </div>
                        </div>
                    @endif

                    <div class="relative w-full my-3" style="height: 220px;" wire:ignore>
                        <canvas id="registersChart"></canvas>
                    </div>
                </div>
            </div>

        </div>

        {{-- ============================================================= --}}
        {{-- LISTADO DETALLADO DE TRANSACCIONES DEL PERÍODO                --}}
        {{-- ============================================================= --}}
        <div class="reports-card p-5 space-y-3">
            <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                <div>
                    <h3 class="text-sm font-black text-gray-900 flex items-center gap-2">
                        <x-heroicon-o-receipt-percent class="w-5 h-5 text-orange-600" />
                        Historial de Comprobantes del Período
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Transacciones completadas o cobradas en las fechas indicadas.
                    </p>
                </div>
                <span class="text-xs font-bold text-gray-500">
                    Total: {{ $this->salesList->total() }} ventas
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-600 font-bold border-y border-gray-200">
                        <tr>
                            <th class="py-2.5 px-3">Comprobante</th>
                            <th class="py-2.5 px-3">Fecha y Hora</th>
                            <th class="py-2.5 px-3">Cliente</th>
                            <th class="py-2.5 px-3">Caja de Atención</th>
                            <th class="py-2.5 px-3">Medio de Pago</th>
                            <th class="py-2.5 px-3 text-right">Total</th>
                            <th class="py-2.5 px-3 text-center">Tirilla</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($this->salesList as $sale)
                            <tr class="hover:bg-gray-50">
                                <td class="py-2.5 px-3 font-mono font-bold text-orange-600">
                                    {{ $sale->invoice_number }}
                                </td>
                                <td class="py-2.5 px-3 text-gray-600">
                                    {{ $sale->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="py-2.5 px-3 font-medium text-gray-900">
                                    {{ $sale->customer?->name ?? 'Cliente Mostrador / Varios' }}
                                </td>
                                <td class="py-2.5 px-3 text-gray-700 font-medium">
                                    {{ $sale->cashShift?->register?->name ?? 'Caja Mostrador' }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="uppercase font-bold text-[10px] px-2 py-0.5 rounded
                                        {{ $sale->payment_method === 'cash' ? 'bg-green-100 text-green-800' : '' }}
                                        {{ $sale->payment_method === 'card' ? 'bg-blue-100 text-blue-800' : '' }}
                                        {{ $sale->payment_method === 'transfer' ? 'bg-indigo-100 text-indigo-800' : '' }}
                                        {{ $sale->payment_method === 'credit' ? 'bg-orange-100 text-orange-800' : '' }}
                                        {{ !in_array($sale->payment_method, ['cash','card','transfer','credit']) ? 'bg-gray-100 text-gray-800' : '' }}">
                                        {{ $sale->payment_method }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-black text-gray-900">
                                    ${{ number_format($sale->total, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <a
                                        href="/admin/sales/{{ $sale->id }}/receipt"
                                        target="_blank"
                                        class="inline-flex items-center justify-center p-1 rounded-md text-orange-600 hover:bg-orange-50"
                                        title="Ver Tirilla POS"
                                    >
                                        <x-heroicon-o-printer class="w-4 h-4" />
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-gray-400">
                                    No se encontraron ventas registradas en el período seleccionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginador --}}
            <div class="pt-2 border-t border-gray-100">
                {{ $this->salesList->links() }}
            </div>
        </div>

    </div>

    {{-- Script de Chart.js y reactividad con Livewire --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function reportsModule() {
            return {
                dailyChart: null,
                topChart: null,
                registersChart: null,

                init() {
                    this.$nextTick(() => {
                        this.renderCharts();
                    });

                    // Re-renderizar cuando Livewire actualice las propiedades
                    Livewire.hook('commit', ({ succeed }) => {
                        succeed(() => {
                            this.$nextTick(() => {
                                this.renderCharts();
                            });
                        });
                    });
                },

                renderCharts() {
                    const dailyData = @js($this->dailyHistogram);
                    const topData = @js($this->topProducts);
                    const regData = @js($this->registerStats);

                    // 1. Histograma Diario
                    const ctxDaily = document.getElementById('dailySalesChart');
                    if (ctxDaily) {
                        if (this.dailyChart) {
                            this.dailyChart.destroy();
                        }
                        this.dailyChart = new Chart(ctxDaily, {
                            type: 'bar',
                            data: {
                                labels: dailyData.labels,
                                datasets: [{
                                    label: 'Ventas Diarias ($ COP)',
                                    data: dailyData.values,
                                    backgroundColor: '#ea580c',
                                    borderRadius: 6,
                                    hoverBackgroundColor: '#c2410c',
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return '$ ' + Number(context.raw).toLocaleString('es-CO') + ' COP';
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            callback: function(value) {
                                                if (value >= 1000000) return '$' + (value / 1000000).toFixed(1) + 'M';
                                                if (value >= 1000) return '$' + (value / 1000).toFixed(0) + 'k';
                                                return '$' + value;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }

                    // 2. Top 10 Productos Más Vendidos (Barras Horizontales)
                    const ctxTop = document.getElementById('topProductsChart');
                    if (ctxTop) {
                        if (this.topChart) {
                            this.topChart.destroy();
                        }
                        this.topChart = new Chart(ctxTop, {
                            type: 'bar',
                            data: {
                                labels: topData.labels,
                                datasets: [{
                                    label: 'Total Facturado ($)',
                                    data: topData.amounts,
                                    backgroundColor: '#f97316',
                                    borderRadius: 4,
                                }]
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                const idx = context.dataIndex;
                                                const qty = topData.quantities[idx] || 0;
                                                return '$ ' + Number(context.raw).toLocaleString('es-CO') + ' COP (' + qty + ' uds)';
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        beginAtZero: true,
                                        ticks: {
                                            callback: function(value) {
                                                if (value >= 1000000) return '$' + (value / 1000000).toFixed(1) + 'M';
                                                return '$' + value;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }

                    // 3. Ventas por Caja de Atención (Doughnut / Torta)
                    const ctxReg = document.getElementById('registersChart');
                    if (ctxReg) {
                        if (this.registersChart) {
                            this.registersChart.destroy();
                        }
                        this.registersChart = new Chart(ctxReg, {
                            type: 'doughnut',
                            data: {
                                labels: regData.labels,
                                datasets: [{
                                    data: regData.totals,
                                    backgroundColor: ['#ea580c', '#16a34a', '#0284c7', '#8b5cf6'],
                                    borderWidth: 2,
                                    borderColor: '#ffffff',
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: { boxWidth: 12, font: { size: 11, weight: 'bold' } }
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return context.label + ': $' + Number(context.raw).toLocaleString('es-CO') + ' COP';
                                            }
                                        }
                                    }
                                },
                                cutout: '65%'
                            }
                        });
                    }
                }
            };
        }
    </script>
</x-filament-panels::page>
