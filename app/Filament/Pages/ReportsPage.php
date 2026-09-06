<?php

namespace App\Filament\Pages;

use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\SaleItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

class ReportsPage extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Informes';

    protected static ?string $title = 'Informes y Estadísticas de Ventas';

    protected static ?string $navigationGroup = 'Informes y Estadísticas';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.reports-page';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('reports.view') ?? false;
    }

    // -------------------------------------------------------------------------
    // FILTROS REACTIVOS DE TIEMPO Y CAJA
    // -------------------------------------------------------------------------
    /** @var string 'today', 'yesterday', 'last_7_days', 'this_month', 'last_month', 'custom' */
    public string $datePreset = 'this_month';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?int $selectedRegisterId = null;

    public function mount(): void
    {
        $this->applyPreset('this_month');
    }

    public function applyPreset(string $preset): void
    {
        $this->datePreset = $preset;
        $now = Carbon::now();

        switch ($preset) {
            case 'today':
                $this->startDate = $now->format('Y-m-d');
                $this->endDate = $now->format('Y-m-d');
                break;
            case 'yesterday':
                $this->startDate = $now->clone()->subDay()->format('Y-m-d');
                $this->endDate = $now->clone()->subDay()->format('Y-m-d');
                break;
            case 'last_7_days':
                $this->startDate = $now->clone()->subDays(6)->format('Y-m-d');
                $this->endDate = $now->format('Y-m-d');
                break;
            case 'this_month':
                $this->startDate = $now->clone()->startOfMonth()->format('Y-m-d');
                $this->endDate = $now->clone()->endOfMonth()->format('Y-m-d');
                break;
            case 'last_month':
                $this->startDate = $now->clone()->subMonth()->startOfMonth()->format('Y-m-d');
                $this->endDate = $now->clone()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'custom':
                // Conserva las fechas ingresadas manualmente
                break;
        }

        $this->resetPage();
    }

    public function updatedStartDate(): void
    {
        $this->datePreset = 'custom';
        $this->resetPage();
    }

    public function updatedEndDate(): void
    {
        $this->datePreset = 'custom';
        $this->resetPage();
    }

    public function updatedSelectedRegisterId(): void
    {
        $this->resetPage();
    }

    /**
     * Devuelve el rango [inicio, fin] como objetos Carbon.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function getActiveDateRange(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : Carbon::now()->startOfMonth()->startOfDay();
        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : Carbon::now()->endOfDay();

        return [$start, $end];
    }

    /**
     * Scope base para consultar ventas válidas dentro del rango y caja.
     */
    protected function baseSalesQuery()
    {
        [$start, $end] = $this->getActiveDateRange();

        return Sale::whereBetween('sales.created_at', [$start, $end])
            ->whereIn('sales.status', ['completed', 'paid', 'delivered'])
            ->when($this->selectedRegisterId, function ($q) {
                $q->whereHas('cashShift', fn ($sq) => $sq->where('cash_register_id', $this->selectedRegisterId));
            });
    }

    /**
     * TARJETAS DE MÉTRICAS CLAVE (KPIs)
     */
    public function getKpisProperty(): array
    {
        $sales = $this->baseSalesQuery()->get();

        $totalSales = (float) $sales->sum('total');
        $ordersCount = $sales->count();
        $averageTicket = $ordersCount > 0 ? round($totalSales / $ordersCount, 2) : 0.0;

        $saleIds = $sales->pluck('id');
        $unitsSold = (float) SaleItem::whereIn('sale_id', $saleIds)->sum('quantity');

        $cashTotal = (float) $sales->where('payment_method', 'cash')->sum('total');
        $cardTotal = (float) $sales->where('payment_method', 'card')->sum('total');
        $transferTotal = (float) $sales->where('payment_method', 'transfer')->sum('total');
        $creditTotal = (float) $sales->where('payment_method', 'credit')->sum('total');

        return [
            'total_sales' => $totalSales,
            'orders_count' => $ordersCount,
            'average_ticket' => $averageTicket,
            'units_sold' => $unitsSold,
            'cash_total' => $cashTotal,
            'card_total' => $cardTotal,
            'transfer_total' => $transferTotal,
            'credit_total' => $creditTotal,
        ];
    }

    /**
     * HISTOGRAMA DIARIO DE VENTAS:
     * Retorna los días dentro del período y las ventas acumuladas por cada fecha.
     */
    public function getDailyHistogramProperty(): array
    {
        [$start, $end] = $this->getActiveDateRange();

        $salesByDay = $this->baseSalesQuery()
            ->selectRaw('DATE(sales.created_at) as sale_date, SUM(sales.total) as daily_total, COUNT(sales.id) as daily_count')
            ->groupBy(DB::raw('DATE(sales.created_at)'))
            ->orderBy('sale_date')
            ->pluck('daily_total', 'sale_date')
            ->toArray();

        $labels = [];
        $values = [];

        $diffDays = $start->diffInDays($end);
        $current = $start->clone();

        if ($diffDays <= 45) {
            while ($current->lte($end)) {
                $dateKey = $current->format('Y-m-d');
                $labels[] = $current->format('d M');
                $values[] = (float) ($salesByDay[$dateKey] ?? 0);
                $current->addDay();
            }
        } else {
            foreach ($salesByDay as $dateKey => $amount) {
                $labels[] = Carbon::parse($dateKey)->format('d M');
                $values[] = (float) $amount;
            }
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * TOP 10 PRODUCTOS MÁS VENDIDOS:
     * Ranking de artículos con mayor facturación y unidades en el período.
     */
    public function getTopProductsProperty(): array
    {
        [$start, $end] = $this->getActiveDateRange();

        $top = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereBetween('sales.created_at', [$start, $end])
            ->whereIn('sales.status', ['completed', 'paid', 'delivered'])
            ->when($this->selectedRegisterId, function ($q) {
                $q->join('cash_shifts', 'sales.cash_shift_id', '=', 'cash_shifts.id')
                    ->where('cash_shifts.cash_register_id', $this->selectedRegisterId);
            })
            ->select(
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as total_qty'),
                DB::raw('SUM(sale_items.subtotal) as total_amount')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        $labels = [];
        $amounts = [];
        $quantities = [];

        foreach ($top as $item) {
            $labels[] = strlen($item->name) > 26 ? substr($item->name, 0, 24).'...' : $item->name;
            $amounts[] = (float) $item->total_amount;
            $quantities[] = (float) $item->total_qty;
        }

        return [
            'labels' => $labels,
            'amounts' => $amounts,
            'quantities' => $quantities,
            'items' => $top,
        ];
    }

    /**
     * RECAUDACIÓN POR CAJA DE ATENCIÓN:
     * Compara las ventas registradas por cada caja y determina cuál es la ganadora.
     */
    public function getRegisterStatsProperty(): array
    {
        [$start, $end] = $this->getActiveDateRange();

        $registers = CashRegister::where('is_active', true)->get();
        $stats = [];
        $labels = [];
        $totals = [];
        $orderCounts = [];

        $winnerName = null;
        $maxTotal = -1;

        foreach ($registers as $reg) {
            $query = Sale::whereBetween('created_at', [$start, $end])
                ->whereIn('status', ['completed', 'paid', 'delivered'])
                ->whereHas('cashShift', fn ($q) => $q->where('cash_register_id', $reg->id));

            $total = (float) $query->sum('total');
            $orders = $query->count();

            $labels[] = $reg->name;
            $totals[] = $total;
            $orderCounts[] = $orders;

            if ($total > $maxTotal && $total > 0) {
                $maxTotal = $total;
                $winnerName = $reg->name;
            }

            $stats[] = [
                'register' => $reg,
                'total' => $total,
                'orders' => $orders,
            ];
        }

        return [
            'labels' => $labels,
            'totals' => $totals,
            'order_counts' => $orderCounts,
            'stats' => $stats,
            'winner_name' => $winnerName,
            'winner_total' => max(0, $maxTotal),
        ];
    }

    /**
     * Listado paginado de transacciones del período seleccionado
     */
    public function getSalesListProperty()
    {
        return $this->baseSalesQuery()
            ->with(['customer', 'user', 'cashShift.register'])
            ->latest('sales.created_at')
            ->paginate(10);
    }

    public function getRegistersListProperty(): Collection
    {
        return CashRegister::where('is_active', true)->orderBy('name')->get();
    }

    public function downloadPdf()
    {
        [$start, $end] = $this->getActiveDateRange();

        $data = [
            'startDate' => $start->format('d/m/Y'),
            'endDate' => $end->format('d/m/Y'),
            'kpis' => $this->kpis,
            'topProducts' => $this->top_products,
            'registerStats' => $this->register_stats,
            'preset' => $this->datePreset,
        ];

        $pdf = Pdf::loadView('pdf.sales-report', $data)
            ->setPaper('letter', 'portrait');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'informe-ventas-'.Carbon::now()->format('Y-m-d').'.pdf'
        );
    }
}
