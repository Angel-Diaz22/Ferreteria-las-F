<?php

namespace App\Filament\Pages;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Models\Warehouse;
use App\Services\PosService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Livewire\WithPagination;

/**
 * ============================================================================
 * PÁGINA PERSONALIZADA: PosTerminal (Terminal de Punto de Venta en Vivo)
 * ============================================================================
 * FLUJO DE FERRETERÍA DE 3 PASOS:
 * 1. PASO 1 (Mostrador / Atención):
 *    El asesor arma el pedido, reserva las existencias en bodega, emite la
 *    ÚNICA tirilla física POS (REM-XXXXXX) y envía al cliente a Caja Central.
 * 2. PASO 2 (Caja Central / Gerente):
 *    El gerente ve los pedidos pendientes en cola, recibe el pago (efectivo,
 *    tarjeta, crédito, transfer), asienta el Kardex en el sistema y
 *    ESTAMPAR EL SELLO FÍSICO "PAGADO" en la tirilla del cliente.
 * 3. PASO 3 (Despacho / Entrega):
 *    El cliente regresa a mostrador con su tirilla sellada. El asesor valida
 *    el sello físico + estado 'paid' en pantalla (doble validación anti-fraude),
 *    empaca los artículos y confirma la entrega ('delivered').
 */
class PosTerminal extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationLabel = 'Punto de Venta (POS)';

    protected static ?string $title = 'Terminal de Venta';

    protected static ?string $navigationGroup = 'Ventas y Clientes';

    protected static ?int $navigationSort = 0; // Primer ítem del menú para acceso inmediato del cajero

    protected static string $view = 'filament.pages.pos-terminal';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('pos.access') ?? false;
    }

    // -------------------------------------------------------------------------
    // CONTROL DE PESTAÑAS (Roles y Fases del Flujo)
    // -------------------------------------------------------------------------
    /** @var string 'terminal' (Mostrador), 'caja' (Caja Central), 'despacho' (Entregas) */
    public string $currentTab = 'terminal';

    // -------------------------------------------------------------------------
    // ESTADO DEL COMPONENTE (Propiedades reactivas de Livewire)
    // -------------------------------------------------------------------------
    public string $search = '';

    public ?int $selectedCategory = null;

    public int $warehouseId = 1;

    public ?int $customerId = null;

    public string $customerSearch = '';

    /** @var array<int, array{product_id: int, name: string, sku: string, unit: string, unit_price: float, original_price: float, quantity: float, tax_rate: float, discount: float, subtotal: float, stock: float}> */
    public array $cart = [];

    // Modal de Cobro Directo en Mostrador (Para ventas inmediatas 1-paso)
    public bool $paymentModalOpen = false;

    public string $paymentMethod = 'cash'; // cash, card, credit, transfer

    public string $paidAmount = '0';

    public string $notes = '';

    // Modal de Venta Exitosa / Ticket Directo
    public bool $saleSuccessModalOpen = false;

    public ?int $lastSaleId = null;

    public string $lastInvoiceNumber = '';

    public float $lastChange = 0.0;

    public float $lastTotal = 0.0;

    // -------------------------------------------------------------------------
    // ESTADO DEL MODAL DE PEDIDO GENERADO (PASO 1: ATENCIÓN)
    // -------------------------------------------------------------------------
    public bool $orderGeneratedModalOpen = false;

    public ?int $generatedSaleId = null;

    public string $generatedInvoiceNumber = '';

    public float $generatedTotal = 0.0;

    // -------------------------------------------------------------------------
    // ESTADO DEL MODAL DE COBRO EN CAJA CENTRAL (PASO 2: GERENTE)
    // -------------------------------------------------------------------------
    public bool $cashierPaymentModalOpen = false;

    public ?int $selectedSaleIdForPayment = null;

    public ?Sale $selectedSaleForPayment = null;

    public string $cashierPaymentMethod = 'cash';

    public string $cashierPaidAmount = '0';

    public string $cashierNotes = '';

    // -------------------------------------------------------------------------
    // ESTADO DEL MODAL DE CREACIÓN RÁPIDA DE CLIENTE
    // -------------------------------------------------------------------------
    /**
     * Controla si el modal de cliente rápido está visible.
     * Solo el cajero lo ve; el admin puede completar los datos completos
     * desde el módulo Clientes en el menú lateral.
     */
    public bool $quickCustomerModalOpen = false;

    /** Nombre del nuevo cliente — campo OBLIGATORIO */
    public string $quickCustomerName = '';

    /** Teléfono del cliente — opcional (útil para contacto post-venta) */
    public string $quickCustomerPhone = '';

    /** Correo electrónico — opcional (útil para remisiones digitales) */
    public string $quickCustomerEmail = '';

    /** Errores de validación del formulario rápido */
    public array $quickCustomerErrors = [];

    // -------------------------------------------------------------------------
    // CONTROL DE CAJAS DE ATENCIÓN Y TURNOS (EXCLUSIÓN MUTUA)
    // -------------------------------------------------------------------------
    public ?int $activeCashRegisterId = null;

    public ?int $activeCashShiftId = null;

    public bool $selectRegisterModalOpen = false;

    public bool $closeShiftModalOpen = false;

    public string $shiftOpeningAmount = '0';

    public string $shiftClosingAmount = '0';

    public string $shiftClosingNotes = '';

    /**
     * HOOK mount(): Se ejecuta una sola vez cuando el cajero ingresa a la pantalla.
     */
    public function mount(): void
    {
        $defaultWarehouse = Warehouse::where('is_active', true)->first();
        if ($defaultWarehouse) {
            $this->warehouseId = $defaultWarehouse->id;
        }

        $this->cart = [];
        $this->paymentMethod = 'cash';
        $this->paidAmount = '0';

        $user = auth()->user();

        // 1. REGLA DE ORO: El Administrador entra DIRECTO a la Caja Principal configurada (o Recaudadora/Central)
        if ($user?->hasRole('admin')) {
            $mainRegister = CashRegister::where('is_active', true)->where('is_main', true)->first();

            if (! $mainRegister) {
                $mainRegister = CashRegister::where('is_active', true)->where('type', CashRegister::TYPE_CASHIER)->first();
            }

            if (! $mainRegister) {
                $mainRegister = CashRegister::where('is_active', true)
                    ->where(function ($q) {
                        $q->where('name', 'like', '%Caja 3%')
                            ->orWhere('name', 'like', '%Central%')
                            ->orWhere('name', 'like', '%Patio%');
                    })->first();
            }

            if (! $mainRegister) {
                $mainRegister = CashRegister::where('is_active', true)->orderBy('display_order')->first();
            }

            if (! $mainRegister) {
                $mainRegister = CashRegister::orderByDesc('id')->first();
            }

            if ($mainRegister) {
                $this->activeCashRegisterId = $mainRegister->id;

                // Buscar si ya tiene un turno abierto en la caja principal
                $openShift = CashShift::where('cash_register_id', $mainRegister->id)
                    ->where('user_id', $user->id)
                    ->where('status', 'open')
                    ->first();

                if (! $openShift) {
                    $openShift = CashShift::create([
                        'cash_register_id' => $mainRegister->id,
                        'user_id' => $user->id,
                        'opening_amount' => 0.0,
                        'status' => 'open',
                        'opened_at' => now(),
                    ]);
                }

                $this->activeCashShiftId = $openShift->id;
                $this->selectRegisterModalOpen = false;

                return;
            }
        }

        // 2. Usuarios de mostrador (Cajeros / Soporte):
        // Deben seleccionar cuál caja de atención usarán (Caja 1 o Caja 2)
        $openShift = CashShift::where('user_id', auth()->id())
            ->where('status', 'open')
            ->latest()
            ->first();

        if ($openShift) {
            $this->activeCashShiftId = $openShift->id;
            $this->activeCashRegisterId = $openShift->cash_register_id;
            $this->selectRegisterModalOpen = false;
        } else {
            $this->selectRegisterModalOpen = true;
        }
    }

    /**
     * SELECCIÓN DE CAJA DE ATENCIÓN (EXCLUSIÓN MUTUA):
     * Valida que ninguna otra terminal tenga un turno abierto en la caja seleccionada.
     */
    public function selectCashRegister(int $registerId): void
    {
        $register = CashRegister::find($registerId);
        if (! $register || ! $register->is_active) {
            Notification::make()
                ->title('Caja no disponible')
                ->body('La caja de atención seleccionada no está activa o no existe.')
                ->danger()
                ->send();

            return;
        }

        // Si es un usuario no-administrador intentando acceder a una caja recaudadora
        if ($register->isCashier() && ! auth()->user()?->hasRole('admin')) {
            Notification::make()
                ->title('⛔ Acceso Restringido')
                ->body("La {$register->name} es una Caja Recaudadora y es de uso exclusivo del Administrador. Por favor seleccione un puesto de atención o mostrador.")
                ->danger()
                ->duration(8000)
                ->send();

            return;
        }

        // 1. REGLA DE ORO: Validar si otro usuario ya está operando esta caja
        $existingShift = CashShift::where('cash_register_id', $registerId)
            ->where('status', 'open')
            ->where('user_id', '!=', auth()->id())
            ->with('user')
            ->first();

        if ($existingShift) {
            $operatorName = $existingShift->user?->name ?? 'otro cajero';
            $openedTime = $existingShift->opened_at ? $existingShift->opened_at->format('H:i') : '';
            Notification::make()
                ->title('⛔ Caja Ocupada')
                ->body("La {$register->name} ya está en uso por {$operatorName}".($openedTime ? " desde las {$openedTime}" : '').'. Seleccione otra caja disponible.')
                ->danger()
                ->duration(8000)
                ->send();

            return;
        }

        // 2. Si el usuario actual ya tenía esta misma caja abierta, continuar el turno
        $myExistingShift = CashShift::where('cash_register_id', $registerId)
            ->where('status', 'open')
            ->where('user_id', auth()->id())
            ->first();

        if ($myExistingShift) {
            $this->activeCashShiftId = $myExistingShift->id;
            $this->activeCashRegisterId = $register->id;
            $this->selectRegisterModalOpen = false;

            Notification::make()
                ->title("Turno Retomado: {$register->name}")
                ->body("Continuando sesión en {$register->name}.")
                ->success()
                ->send();

            return;
        }

        // 3. Abrir nuevo turno exclusivo:
        // Cajas 1 y 2 NO manejan dinero, su base inicial es estrictamente $0.0 COP
        $opening = $register->handlesCash() ? max(0.0, (float) $this->shiftOpeningAmount) : 0.0;

        $newShift = CashShift::create([
            'cash_register_id' => $register->id,
            'user_id' => auth()->id(),
            'opening_amount' => $opening,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->activeCashShiftId = $newShift->id;
        $this->activeCashRegisterId = $register->id;
        $this->selectRegisterModalOpen = false;

        $bodyMsg = $register->handlesCash()
            ? "Operando desde {$register->name} con base inicial de \$".number_format($opening, 0, ',', '.').' COP.'
            : "Puesto activado en {$register->name} (Atención y mostrador - Sin manejo de dinero).";

        Notification::make()
            ->title('Turno Iniciado con Éxito')
            ->body($bodyMsg)
            ->success()
            ->send();
    }

    /**
     * Abre el modal para realizar arqueo y liberar la caja de atención.
     */
    public function openCloseShiftModal(): void
    {
        if (! $this->activeCashShiftId) {
            $this->selectRegisterModalOpen = true;

            return;
        }

        $shift = CashShift::find($this->activeCashShiftId);
        if (! $shift) {
            $this->selectRegisterModalOpen = true;

            return;
        }

        $register = $this->activeCashRegister;

        // Cajas 1 y 2 no manejan dinero: liberación directa sin arqueo de efectivo
        if ($register && ! $register->handlesCash()) {
            $shift->update(['expected_amount' => 0.0]);
            $this->shiftClosingAmount = '0';
            $this->shiftClosingNotes = '';
            $this->closeShiftModalOpen = true;

            return;
        }

        // Calcular efectivo recaudado en este turno (Exclusivo para Caja 3 / cajas recaudadoras)
        $cashSales = (float) Sale::where('cash_shift_id', $shift->id)
            ->where('payment_method', 'cash')
            ->whereIn('status', ['completed', 'paid', 'delivered'])
            ->sum('paid_amount');

        $cashChanges = (float) Sale::where('cash_shift_id', $shift->id)
            ->where('payment_method', 'cash')
            ->whereIn('status', ['completed', 'paid', 'delivered'])
            ->sum('change_amount');

        $netCash = max(0.0, $cashSales - $cashChanges);
        $expected = (float) $shift->opening_amount + $netCash;

        $shift->update(['expected_amount' => $expected]);

        $this->shiftClosingAmount = (string) $expected;
        $this->shiftClosingNotes = '';
        $this->closeShiftModalOpen = true;
    }

    /**
     * Confirma el arqueo o liberación de puesto, cierra el turno y libera la caja para otros usuarios.
     */
    public function confirmCloseShift(): void
    {
        if (! $this->activeCashShiftId) {
            return;
        }

        $shift = CashShift::find($this->activeCashShiftId);
        $register = $this->activeCashRegister;

        if ($shift) {
            if ($register && ! $register->handlesCash()) {
                $closing = 0.0;
                $diff = 0.0;
            } else {
                $closing = max(0.0, (float) $this->shiftClosingAmount);
                $expected = (float) $shift->expected_amount;
                $diff = round($closing - $expected, 2);
            }

            $shift->update([
                'closing_amount' => $closing,
                'difference' => $diff,
                'status' => 'closed',
                'closed_at' => now(),
                'notes' => $this->shiftClosingNotes,
            ]);
        }

        $registerName = $register?->name ?? 'Caja de atención';

        $this->activeCashShiftId = null;
        $this->activeCashRegisterId = null;
        $this->closeShiftModalOpen = false;

        // Si es admin, vuelve a su Caja 3 por defecto
        if (auth()->user()?->hasRole('admin')) {
            $this->mount();
        } else {
            $this->selectRegisterModalOpen = true;
        }

        Notification::make()
            ->title('Turno Finalizado')
            ->body("{$registerName} ha sido liberada exitosamente.")
            ->success()
            ->send();
    }

    /**
     * LIBERACIÓN FORZADA (Solo Administrador):
     * Permite al dueño o administrador liberar una caja si un cajero la dejó bloqueada.
     */
    public function forceReleaseRegister(int $registerId): void
    {
        if (! auth()->user()?->hasRole('admin')) {
            Notification::make()->title('Acción no autorizada')->danger()->send();

            return;
        }

        $openShift = CashShift::where('cash_register_id', $registerId)
            ->where('status', 'open')
            ->first();

        if ($openShift) {
            $openShift->update([
                'status' => 'closed',
                'closed_at' => now(),
                'notes' => 'Liberación forzosa realizada por el Administrador.',
            ]);
        }

        Notification::make()
            ->title('Caja Liberada')
            ->body('La caja de atención ha sido desbloqueada para su uso.')
            ->success()
            ->send();
    }

    /**
     * ESCÁNER DE CÓDIGO DE BARRAS:
     * Cuando el lector USB lee el código y envía una tecla Enter, este método
     * busca por código de barras exacto o SKU y lo añade directamente al carrito.
     */
    public function scanBarcode(): void
    {
        $query = trim($this->search);
        if (empty($query)) {
            return;
        }

        // Buscar coincidencia exacta por barcode o sku
        $product = Product::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('barcode', $query)
                    ->orWhere('sku', $query);
            })
            ->first();

        if ($product) {
            $this->addToCart($product->id);
            $this->search = '';
        } else {
            Notification::make()
                ->title('Producto no encontrado')
                ->body("No se encontró ningún artículo activo con código o SKU '{$query}'.")
                ->warning()
                ->send();
        }
    }

    /**
     * AÑADIR PRODUCTO AL CARRITO:
     * Verifica stock en la bodega actual del cajero y asigna el precio correspondiente.
     */
    public function addToCart(int $productId): void
    {
        $product = Product::find($productId);
        if (! $product) {
            return;
        }

        // 1. Validar existencia física en la bodega de trabajo
        $stockRecord = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouseId)
            ->first();

        $availableStock = (float) ($stockRecord?->current_stock ?? 0.0);

        if ($availableStock <= 0) {
            Notification::make()
                ->title('Sin existencias')
                ->body("El producto '{$product->name}' no tiene stock disponible en esta bodega.")
                ->danger()
                ->send();

            return;
        }

        // 2. Revisar si ya está en el carrito
        $cartIndex = null;
        foreach ($this->cart as $index => $item) {
            if ($item['product_id'] === $product->id) {
                $cartIndex = $index;
                break;
            }
        }

        if ($cartIndex !== null) {
            // Incrementar cantidad si no sobrepasa el stock
            $currentQty = $this->cart[$cartIndex]['quantity'];
            if ($currentQty + 1 > $availableStock) {
                Notification::make()
                    ->title('Límite de stock alcanzado')
                    ->body("Solo hay {$availableStock} {$product->unit} disponibles en bodega.")
                    ->warning()
                    ->send();

                return;
            }

            $this->cart[$cartIndex]['quantity'] = $currentQty + 1;
            $this->cart[$cartIndex]['subtotal'] = round(($currentQty + 1) * $this->cart[$cartIndex]['unit_price'], 2);
        } else {
            // 3. Determinar precio según la lista asignada al cliente actual
            $unitPrice = $this->resolveProductPrice($product);

            $this->cart[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'unit' => $product->unit,
                'unit_price' => $unitPrice,
                'original_price' => (float) $product->sale_price,
                'quantity' => 1.0,
                'tax_rate' => SystemSetting::isIvaEnabled() ? SystemSetting::getIvaRate() : 0.0,
                'discount' => 0.0,
                'subtotal' => $unitPrice,
                'stock' => $availableStock,
            ];
        }

        $this->paidAmount = (string) $this->getTotalProperty();
    }

    /**
     * MODIFICAR CANTIDADES EN EL CARRITO
     */
    public function updateQuantity(int $index, float $quantity): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        if ($quantity <= 0) {
            $this->removeFromCart($index);

            return;
        }

        $availableStock = $this->cart[$index]['stock'];
        if ($quantity > $availableStock) {
            Notification::make()
                ->title('Stock insuficiente')
                ->body("La cantidad solicitada supera el stock disponible ({$availableStock}).")
                ->warning()
                ->send();
            $quantity = $availableStock;
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->cart[$index]['subtotal'] = round($quantity * $this->cart[$index]['unit_price'], 2);
        $this->paidAmount = (string) $this->getTotalProperty();
    }

    public function incrementQuantity(int $index): void
    {
        if (isset($this->cart[$index])) {
            $this->updateQuantity($index, $this->cart[$index]['quantity'] + 1);
        }
    }

    public function decrementQuantity(int $index): void
    {
        if (isset($this->cart[$index])) {
            $this->updateQuantity($index, $this->cart[$index]['quantity'] - 1);
        }
    }

    public function removeFromCart(int $index): void
    {
        if (isset($this->cart[$index])) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart); // Reindexar array numérico
            $this->paidAmount = (string) $this->getTotalProperty();
        }
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->paidAmount = '0';
        $this->notes = '';
    }

    /**
     * HOOK updatedCustomerId():
     * Si el cajero cambia de cliente a mitad de la venta (ej. selecciona un cliente
     * con tarifa de Constructor o Mayorista), este gancho recalcula inmediatamente
     * los precios unitarios de todos los productos en el carrito.
     */
    public function updatedCustomerId(): void
    {
        foreach ($this->cart as $index => $item) {
            $product = Product::find($item['product_id']);
            if ($product) {
                $newPrice = $this->resolveProductPrice($product);
                $this->cart[$index]['unit_price'] = $newPrice;
                $this->cart[$index]['subtotal'] = round($item['quantity'] * $newPrice, 2);
            }
        }
        $this->paidAmount = (string) $this->getTotalProperty();
    }

    /**
     * Selecciona un cliente desde el buscador rápido y recalcula el carrito.
     */
    public function selectCustomer(?int $id): void
    {
        $this->customerId = $id;
        $this->customerSearch = '';
        $this->updatedCustomerId();
    }

    /**
     * Quita el cliente seleccionado y vuelve a Cliente Mostrador / Ocasional.
     */
    public function clearCustomer(): void
    {
        $this->customerId = null;
        $this->customerSearch = '';
        $this->updatedCustomerId();
    }

    /**
     * HOOK updatedWarehouseId():
     * Si el cajero cambia de bodega, actualiza los límites de stock en el carrito.
     */
    public function updatedWarehouseId(): void
    {
        foreach ($this->cart as $index => $item) {
            $stock = ProductStock::where('product_id', $item['product_id'])
                ->where('warehouse_id', $this->warehouseId)
                ->value('current_stock') ?? 0.0;

            $this->cart[$index]['stock'] = (float) $stock;
        }
    }

    /**
     * RESUELVE EL PRECIO DEL PRODUCTO:
     * Si el cliente tiene lista Mayorista o Constructor, consulta `price_list_items`.
     */
    protected function resolveProductPrice(Product $product): float
    {
        if ($this->customerId) {
            $customer = Customer::find($this->customerId);
            if ($customer && $customer->price_list_id) {
                $specialPrice = PriceListItem::where('price_list_id', $customer->price_list_id)
                    ->where('product_id', $product->id)
                    ->value('price');

                if ($specialPrice !== null) {
                    return (float) $specialPrice;
                }
            }
        }

        return (float) $product->sale_price;
    }

    // -------------------------------------------------------------------------
    // MÓDULO DE COBRO Y PAGO
    // -------------------------------------------------------------------------
    public function openPaymentModal(): void
    {
        if (! $this->canConfirmPayments) {
            Notification::make()
                ->title('⛔ Acción no permitida')
                ->body('Las Cajas 1 y 2 no manejan dinero. Utilice "Generar Pedido e Imprimir Tirilla" para que el cliente pague en Caja Central.')
                ->danger()
                ->send();

            return;
        }

        if (empty($this->cart)) {
            Notification::make()
                ->title('Carrito vacío')
                ->body('Agrega al menos un artículo antes de cobrar.')
                ->warning()
                ->send();

            return;
        }

        $this->paidAmount = (string) $this->getTotalProperty();
        $this->paymentModalOpen = true;
    }

    public function setQuickCash(float $amount): void
    {
        $this->paidAmount = (string) $amount;
    }

    public function processSale(): void
    {
        if (! $this->canConfirmPayments) {
            Notification::make()
                ->title('⛔ Acción no permitida')
                ->body('Solo la Caja Central / Administrador puede procesar cobros directos.')
                ->danger()
                ->send();

            return;
        }

        try {
            $total = $this->getTotalProperty();
            // Convertir paidAmount (string del input HTML) a float para los cálculos
            $paid = (float) $this->paidAmount;

            // Validación previa en frontend: evita enviar petición al servidor innecesariamente
            if ($this->paymentMethod === 'cash' && $paid < $total) {
                Notification::make()
                    ->title('Monto recibido insuficiente')
                    ->body('El efectivo ingresado es menor al total de la cuenta.')
                    ->danger()
                    ->send();

                return;
            }

            // Llamada al servicio atómico transaccional
            $sale = PosService::processSale(
                userId: (int) auth()->id(),
                warehouseId: $this->warehouseId,
                customerId: $this->customerId,
                paymentMethod: $this->paymentMethod,
                items: $this->cart,
                paidAmount: $paid,
                notes: $this->notes,
                cashShiftId: $this->activeCashShiftId
            );

            // Guardar datos para el modal de éxito y descarga de tirilla
            $this->lastSaleId = $sale->id;
            $this->lastInvoiceNumber = $sale->invoice_number;
            $this->lastChange = (float) $sale->change_amount;
            $this->lastTotal = (float) $sale->total;

            $this->paymentModalOpen = false;
            $this->saleSuccessModalOpen = true;

            $this->clearCart();

            Notification::make()
                ->title('¡Venta completada!')
                ->body("Comprobante #{$sale->invoice_number} generado con éxito.")
                ->success()
                ->send();
        } catch (DomainException $e) {
            Notification::make()
                ->title('Error de Negocio')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            // Capturar errores inesperados (base de datos, conexión, etc.)
            Notification::make()
                ->title('Error inesperado al registrar la venta')
                ->body('Detalle técnico: '.$e->getMessage())
                ->danger()
                ->send();

            // Registrar en log para auditoría
            Log::error('POS processSale error', [
                'user_id' => auth()->id(),
                'cart' => $this->cart,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function closeSuccessModal(): void
    {
        $this->saleSuccessModalOpen = false;
        $this->lastSaleId = null;
    }

    // -------------------------------------------------------------------------
    // MÓDULO DE CREACIÓN RÁPIDA DE CLIENTE DESDE EL POS
    // -------------------------------------------------------------------------

    /**
     * Abre el modal de cliente rápido y limpia cualquier dato previo.
     * Este método se llama cuando el cajero hace clic en "+ Nuevo Cliente".
     */
    public function openQuickCustomerModal(): void
    {
        $this->quickCustomerName = '';
        $this->quickCustomerPhone = '';
        $this->quickCustomerEmail = '';
        $this->quickCustomerErrors = [];
        $this->quickCustomerModalOpen = true;
    }

    /**
     * GUARDA EL CLIENTE RÁPIDO:
     *
     * ¿Cómo funciona Validator::make()?
     * - Primer parámetro: array con los datos a validar (los del formulario).
     * - Segundo parámetro: array de REGLAS. 'required' = obligatorio, 'nullable' = acepta nulo,
     *   'email' = debe tener formato válido, 'max:160' = máximo 160 caracteres.
     * - Si falla la validación, mostramos los errores en el blade (no lanzamos excepción).
     *
     * Si todo pasa, creamos el Customer con los datos mínimos y lo seleccionamos
     * automáticamente en el carrito actual, igual que si el cajero lo hubiera
     * elegido desde el dropdown.
     */
    public function saveQuickCustomer(): void
    {
        // Validar solo los campos del formulario rápido
        $validator = Validator::make(
            [
                'name' => $this->quickCustomerName,
                'phone' => $this->quickCustomerPhone,
                'email' => $this->quickCustomerEmail,
            ],
            [
                'name' => ['required', 'string', 'min:2', 'max:160'],
                'phone' => ['nullable', 'string', 'max:20'],
                'email' => ['nullable', 'email', 'max:160'],
            ],
            [
                // Mensajes de error en español para el cajero
                'name.required' => 'El nombre del cliente es obligatorio.',
                'name.min' => 'El nombre debe tener al menos 2 caracteres.',
                'name.max' => 'El nombre no puede superar los 160 caracteres.',
                'email.email' => 'El correo electrónico no tiene un formato válido.',
            ]
        );

        if ($validator->fails()) {
            // Guardamos los errores en la propiedad pública para mostrarlos en el blade
            $this->quickCustomerErrors = $validator->errors()->toArray();

            return;
        }

        // Crear el cliente con datos mínimos
        // 'document' ahora es nullable (migración), así que funciona sin él
        $customer = Customer::create([
            'name' => trim($this->quickCustomerName),
            'phone' => $this->quickCustomerPhone ?: null,
            'email' => $this->quickCustomerEmail ?: null,
            'is_active' => true,
            // Los demás campos (document, address, credit_limit, etc.) se pueden
            // completar después desde el módulo de Clientes completo en el menú lateral
        ]);

        // Seleccionar automáticamente el nuevo cliente en el carrito actual
        $this->customerId = $customer->id;

        // Si hay productos en el carrito, recalcular precios con la tarifa del nuevo cliente
        // (usualmente detal, ya que no tiene precio especial asignado aún)
        $this->updatedCustomerId();

        // Cerrar el modal y confirmar con notificación
        $this->quickCustomerModalOpen = false;
        $this->quickCustomerErrors = [];

        Notification::make()
            ->title("Cliente '{$customer->name}' registrado")
            ->body('Creado y seleccionado en el carrito. Completa sus datos desde el módulo de Clientes.')
            ->success()
            ->send();
    }

    /**
     * Cierra el modal de cliente rápido sin guardar.
     */
    public function closeQuickCustomerModal(): void
    {
        $this->quickCustomerModalOpen = false;
        $this->quickCustomerErrors = [];
    }

    // -------------------------------------------------------------------------
    // FLUJO DE 3 PASOS: ATENCIÓN ➔ GERENCIA ➔ DESPACHO
    // -------------------------------------------------------------------------

    public function switchTab(string $tab): void
    {
        if ($tab === 'caja' && ! $this->canConfirmPayments) {
            Notification::make()
                ->title('⛔ Acceso Restringido')
                ->body('El módulo de confirmación de pagos es exclusivo para el Administrador / Caja Central. Las Cajas 1 y 2 son puestos de atención.')
                ->danger()
                ->send();

            $this->currentTab = 'terminal';

            return;
        }

        $this->currentTab = in_array($tab, ['terminal', 'caja', 'despacho']) ? $tab : 'terminal';
    }

    /**
     * PASO 1 - CAJA DE ATENCIÓN (MOSTRADOR):
     * Crea el pedido en estado 'pending_payment', reserva el stock físico
     * en la bodega para evitar sobreventas, y emite la ÚNICA tirilla física POS
     * que el cliente llevará a la Caja Central para pagar.
     */
    public function generateOrderAndPrint(): void
    {
        if (empty($this->cart)) {
            Notification::make()
                ->title('Carrito vacío')
                ->body('Agregue al menos un producto al carrito antes de generar el pedido.')
                ->warning()
                ->send();

            return;
        }

        try {
            // Invoca la transacción atómica que genera REM-XXXXXX y aparta stock
            $sale = PosService::createPendingOrder(
                userId: (int) auth()->id(),
                warehouseId: $this->warehouseId,
                customerId: $this->customerId,
                items: $this->cart,
                notes: $this->notes,
                cashShiftId: $this->activeCashShiftId
            );

            // Cargar datos para el modal de pedido generado
            $this->generatedSaleId = $sale->id;
            $this->generatedInvoiceNumber = $sale->invoice_number;
            $this->generatedTotal = (float) $sale->total;

            $this->orderGeneratedModalOpen = true;
            $this->clearCart();

            Notification::make()
                ->title("Pedido #{$sale->invoice_number} Generado con Éxito")
                ->body('Tirilla lista para impresión. Entregue la tirilla al cliente para que pase a Caja Central con el Gerente a realizar el pago y recibir el sello.')
                ->success()
                ->duration(10000)
                ->send();
        } catch (DomainException $e) {
            Notification::make()
                ->title('Validación de Inventario')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error inesperado al generar pedido')
                ->body('Detalle: '.$e->getMessage())
                ->danger()
                ->send();

            Log::error('POS generateOrderAndPrint error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function closeOrderGeneratedModal(): void
    {
        $this->orderGeneratedModalOpen = false;
        $this->generatedSaleId = null;
        $this->generatedInvoiceNumber = '';
        $this->generatedTotal = 0.0;
    }

    /**
     * PASO 2 - CAJA CENTRAL (GERENTE):
     * Abre el modal para registrar el cobro del pedido pendiente.
     */
    public function openCashierPaymentModal(int $saleId): void
    {
        $sale = Sale::with(['customer', 'items.product', 'user', 'warehouse'])->find($saleId);

        if (! $sale || $sale->status !== 'pending_payment') {
            Notification::make()
                ->title('Pedido no disponible')
                ->body('Este pedido ya no está pendiente de pago o no existe en el sistema.')
                ->warning()
                ->send();

            return;
        }

        $this->selectedSaleIdForPayment = $sale->id;
        $this->selectedSaleForPayment = $sale;
        $this->cashierPaymentMethod = 'cash';
        $this->cashierPaidAmount = (string) $sale->total;
        $this->cashierNotes = '';
        $this->cashierPaymentModalOpen = true;
    }

    public function setCashierQuickCash(float $amount): void
    {
        $this->cashierPaidAmount = (string) $amount;
    }

    /**
     * PASO 2 - CAJA CENTRAL (GERENTE):
     * Valida el dinero ingresado, asienta la salida en el Kardex y pasa el pedido a 'paid'.
     * Importante: El Gerente estampa físicamente la tirilla del cliente con sello de tinta.
     */
    public function confirmCashierPayment(): void
    {
        if (! $this->canConfirmPayments) {
            Notification::make()
                ->title('⛔ Acción no autorizada')
                ->body('Solo la Caja Central / Administrador puede confirmar pagos.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->selectedSaleForPayment) {
            return;
        }

        try {
            $total = (float) $this->selectedSaleForPayment->total;
            $paid = (float) $this->cashierPaidAmount;

            if ($this->cashierPaymentMethod === 'cash' && $paid < $total) {
                Notification::make()
                    ->title('Monto insuficiente')
                    ->body('El efectivo ingresado es menor al total a pagar.')
                    ->danger()
                    ->send();

                return;
            }

            $sale = PosService::confirmPayment(
                sale: $this->selectedSaleForPayment,
                cashierUserId: (int) auth()->id(),
                paymentMethod: $this->cashierPaymentMethod,
                paidAmount: $paid,
                cashShiftId: $this->activeCashShiftId
            );

            $this->cashierPaymentModalOpen = false;
            $this->selectedSaleForPayment = null;
            $this->selectedSaleIdForPayment = null;

            Notification::make()
                ->title("¡Pago de Pedido #{$sale->invoice_number} Confirmado!")
                ->body('⚠️ ESTAMPE EL SELLO FÍSICO "PAGADO" en la tirilla del cliente para que pueda retirar en Despacho.')
                ->success()
                ->duration(12000)
                ->send();
        } catch (DomainException $e) {
            Notification::make()
                ->title('Error al Registrar Pago')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error inesperado al cobrar')
                ->body($e->getMessage())
                ->danger()
                ->send();

            Log::error('POS confirmCashierPayment error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function closeCashierPaymentModal(): void
    {
        $this->cashierPaymentModalOpen = false;
        $this->selectedSaleForPayment = null;
        $this->selectedSaleIdForPayment = null;
    }

    /**
     * PASO 3 - DESPACHO (ATENCIÓN / BODEGA):
     * Valida la regla anti-fraude: Verifica que el pedido esté en estado 'paid' en el sistema
     * (y físicamente sellado en la tirilla). Entrega los artículos y marca como 'delivered'.
     */
    public function dispatchOrder(int $saleId): void
    {
        $sale = Sale::find($saleId);

        if (! $sale) {
            return;
        }

        try {
            PosService::dispatchOrder($sale, dispatchUserId: (int) auth()->id());

            Notification::make()
                ->title("Pedido #{$sale->invoice_number} Despachado")
                ->body('Mercancía entregada con éxito al cliente. Ciclo de venta completado.')
                ->success()
                ->send();
        } catch (DomainException $e) {
            Notification::make()
                ->title('¡Alerta Anti-Fraude!')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * ANULAR PEDIDO PENDIENTE:
     * Si el cliente desiste de la compra antes de pagar, se anula el pedido
     * y el stock reservado se reintegra inmediatamente a la bodega.
     */
    public function cancelPendingOrder(int $saleId): void
    {
        $sale = Sale::find($saleId);

        if (! $sale) {
            return;
        }

        try {
            PosService::cancelPendingOrder(
                sale: $sale,
                cancelledByUserId: (int) auth()->id(),
                reason: 'Desistimiento del cliente en mostrador'
            );

            Notification::make()
                ->title("Pedido #{$sale->invoice_number} Anulado")
                ->body('El pedido fue cancelado y las existencias apartadas se regresaron al inventario disponible.')
                ->warning()
                ->send();
        } catch (DomainException $e) {
            Notification::make()
                ->title('No se pudo anular')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // -------------------------------------------------------------------------
    // PROPIEDADES COMPUTADAS (Getters accesibles desde la vista Blade)
    // -------------------------------------------------------------------------
    public function getPendingOrdersProperty(): Collection
    {
        return Sale::pendingPayment()
            ->with(['customer', 'user', 'warehouse', 'items.product'])
            ->orderByDesc('id')
            ->get();
    }

    public function getPaidOrdersProperty(): Collection
    {
        return Sale::paid()
            ->with(['customer', 'user', 'warehouse', 'items.product'])
            ->orderByDesc('id')
            ->get();
    }

    public function getPendingCountProperty(): int
    {
        return Sale::pendingPayment()->count();
    }

    public function getPaidCountProperty(): int
    {
        return Sale::paid()->count();
    }

    public function getCashierChangeProperty(): float
    {
        if (! $this->selectedSaleForPayment || $this->cashierPaymentMethod !== 'cash') {
            return 0.0;
        }

        return max(0.0, round((float) $this->cashierPaidAmount - (float) $this->selectedSaleForPayment->total, 2));
    }

    public function getSubtotalProperty(): float
    {
        $sum = 0.0;
        foreach ($this->cart as $item) {
            $sum += (float) $item['subtotal'];
        }

        return round($sum, 2);
    }

    public function getIsIvaEnabledProperty(): bool
    {
        return SystemSetting::isIvaEnabled();
    }

    public function getTaxAmountProperty(): float
    {
        if (! SystemSetting::isIvaEnabled()) {
            return 0.0;
        }

        $tax = 0.0;
        $defaultRate = SystemSetting::getIvaRate();
        foreach ($this->cart as $item) {
            $lineSubtotal = (float) $item['subtotal'];
            $rate = (float) ($item['tax_rate'] ?? $defaultRate);
            $tax += round($lineSubtotal * ($rate / 100), 2);
        }

        return round($tax, 2);
    }

    public function getTotalProperty(): float
    {
        return round($this->getSubtotalProperty() + $this->getTaxAmountProperty(), 2);
    }

    public function getChangeAmountProperty(): float
    {
        if ($this->paymentMethod !== 'cash') {
            return 0.0;
        }

        return max(0.0, round((float) $this->paidAmount - $this->getTotalProperty(), 2));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function setCategory(?int $categoryId): void
    {
        $this->selectedCategory = $categoryId;
        $this->resetPage();
    }

    public function getProductsProperty(): LengthAwarePaginator
    {
        return Product::where('is_active', true)
            ->when($this->selectedCategory, fn ($q) => $q->where('category_id', $this->selectedCategory))
            ->when(! empty($this->search), function ($q) {
                $term = '%'.trim($this->search).'%';
                $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->where(function ($sub) use ($term, $likeOp) {
                    $sub->where('name', $likeOp, $term)
                        ->orWhere('sku', $likeOp, $term)
                        ->orWhere('barcode', $likeOp, $term);
                });
            })
            ->with(['brand', 'stocks' => fn ($q) => $q->where('warehouse_id', $this->warehouseId)])
            ->orderBy('name')
            ->paginate(9);
    }

    public function getActiveCashRegisterProperty(): ?CashRegister
    {
        return $this->activeCashRegisterId ? CashRegister::find($this->activeCashRegisterId) : null;
    }

    public function getActiveCashShiftProperty(): ?CashShift
    {
        return $this->activeCashShiftId ? CashShift::find($this->activeCashShiftId) : null;
    }

    /**
     * Determina si el usuario actual puede cobrar y confirmar pagos en el sistema.
     * Solo el Administrador o quien opere la Caja 3 (Caja Central).
     */
    public function getCanConfirmPaymentsProperty(): bool
    {
        return (bool) (auth()->user()?->hasRole('admin') || ($this->activeCashRegister?->handlesCash() ?? false));
    }

    /**
     * Retorna las cajas activas con su estado de disponibilidad en vivo:
     * - Si el usuario es administrador, ve todas las cajas.
     * - Si el usuario es cajero/soporte, SOLO ve las cajas de atención (Caja 1 y Caja 2).
     */
    public function getCashRegistersWithStatusProperty(): array
    {
        $isAdmin = (bool) auth()->user()?->hasRole('admin');

        $query = CashRegister::where('is_active', true)->orderBy('display_order')->orderBy('name');

        if (! $isAdmin) {
            $registers = $query->get()->filter(fn ($reg) => $reg->isAttentionRegister());
        } else {
            $registers = $query->get();
        }

        $activeShifts = CashShift::where('status', 'open')->with('user')->get()->keyBy('cash_register_id');

        $result = [];
        foreach ($registers as $reg) {
            $shift = $activeShifts->get($reg->id);
            if (! $shift) {
                $status = 'free';
                $operator = null;
                $openedAt = null;
            } elseif ($shift->user_id === auth()->id()) {
                $status = 'mine';
                $operator = 'Tú (Turno en curso)';
                $openedAt = $shift->opened_at ? $shift->opened_at->format('H:i') : null;
            } else {
                $status = 'busy';
                $operator = $shift->user?->name ?? 'Otro cajero';
                $openedAt = $shift->opened_at ? $shift->opened_at->format('H:i') : null;
            }

            $result[] = [
                'register' => $reg,
                'status' => $status,
                'operator' => $operator,
                'opened_at' => $openedAt,
                'shift_id' => $shift?->id,
            ];
        }

        return array_values($result);
    }

    public function getCategoriesProperty(): Collection
    {
        return Category::orderBy('name')->get();
    }

    public function getCustomersProperty(): Collection
    {
        return Customer::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Devuelve los clientes filtrados para el autocompletado en el carrito.
     */
    public function getFilteredCustomersProperty(): Collection
    {
        $term = trim($this->customerSearch);
        if ($term === '') {
            return Customer::where('is_active', true)->orderBy('name')->limit(8)->get();
        }

        return Customer::where('is_active', true)
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('document', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    public function getWarehousesProperty(): Collection
    {
        return Warehouse::where('is_active', true)->orderBy('name')->get();
    }

    public function getSelectedCustomerProperty(): ?Customer
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }
}
