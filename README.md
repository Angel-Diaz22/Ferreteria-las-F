# 🔧 Ferretería Las F — ERP / Punto de Venta (POS)

Sistema integral de gestión ferretera con terminal de punto de venta interactivo, control de inventario multicaja y multi-bodega.

**Stack**: Laravel 12 · PHP 8.4 · Filament v3 · Livewire 3 · PostgreSQL · Tailwind CSS

**Producción**: [https://ferreteria-las-f.onrender.com](https://ferreteria-las-f.onrender.com)

---

## Módulos

| Módulo | Descripción |
|--------|-------------|
| **Terminal POS** | Flujo de 3 pasos: Pedido → Pago en Caja Central → Despacho |
| **Productos** | Catálogo con categorías, marcas, stock por bodega, listas de precios |
| **Inventario** | Kardex por bodega, movimientos de entrada/salida, ajustes |
| **Compras** | Registro de compras a proveedores con actualización automática de stock |
| **Cotizaciones** | Generación de cotizaciones con descarga PDF |
| **Clientes** | Directorio de clientes con historial de compras |
| **Cajas y Turnos** | Apertura/cierre de turnos, arqueo de caja, movimientos de efectivo |
| **Reportes** | Dashboard de ventas, estadísticas generales |
| **Configuración** | Datos de empresa, régimen tributario (IVA), ajustes del sistema |

## Reglas de Negocio del POS

- **Cajas de Mostrador** (Cajas 1 y 2): Atención sin cobro, generan pedidos e imprimen tirilla.
- **Caja Central** (Caja 3): Recauda pagos (efectivo, tarjeta, transferencia), maneja arqueo contable.
- Los cajeros solo acceden a cajas de mostrador; el admin entra directo a la Caja Central.

## Instalación Local

```bash
# Clonar
git clone https://github.com/Angel-Diaz22/Ferreteria-las-F.git
cd Ferreteria-las-F

# Dependencias
composer install
npm install && npm run build

# Configurar
cp .env.example .env
php artisan key:generate

# Base de datos (requiere PostgreSQL)
php artisan migrate --seed

# Iniciar
php artisan serve   # o usar Laravel Herd
```

## Credenciales de Demo

| Rol | Email | Contraseña |
|-----|-------|------------|
| Admin | `admin@ferreteria.com` | `password123` |
| Cajero | `cajero@ferreteria.com` | `password123` |

## Tests

```bash
# Suite completa (275 tests, 714 assertions)
php artisan test --compact

# Solo E2E (221 tests, 456 assertions — Tiers 1-4)
php artisan test tests/Feature/E2E --compact

# Tier específico
php artisan test tests/Feature/E2E/Tier1 --compact
```

**Cobertura por Tier:**

| Tier | Enfoque | Tests |
|------|---------|-------|
| Tier 1 | Feature Coverage (F01-F20) | 100 |
| Tier 2 | Boundary & Corner Cases | 100 |
| Tier 3 | Cross-Feature Combinations | 16 |
| Tier 4 | Real-World Application Scenarios | 5 |

## Despliegue (Render.com + Supabase)

El proyecto incluye un `Dockerfile` multi-stage optimizado para Render:
- **Etapa 1**: Node 20 Alpine → `npm run build` (Vite + Tailwind)
- **Etapa 2**: PHP 8.4 FPM Alpine + Nginx + Supervisor

Archivos clave: `Dockerfile`, `render.yaml`, `docker/entrypoint.sh`, `docker/nginx-render.conf`, `docker/supervisord.conf`

Variables de entorno: ver `.env.production.example`

## Estructura del Proyecto

```
app/
├── Filament/
│   ├── Pages/          # PosTerminal, ReportsPage, SettingsPage
│   └── Resources/      # CRUD: Products, Sales, Purchases, Quotes, etc.
├── Http/Controllers/   # QuotePdfController, SaleReceiptController
├── Models/             # 24 modelos Eloquent
└── Services/           # KardexService, PosService
config/
database/
├── migrations/         # Esquema completo PostgreSQL
├── seeders/            # Datos iniciales (admin, cajero, productos)
└── factories/          # Factories para tests
docker/                 # nginx-render.conf, supervisord.conf, entrypoint.sh
resources/views/        # Blade views (POS terminal, reportes, PDFs)
tests/Feature/E2E/      # Suite de 221 tests en 4 tiers
```

## Licencia

Proyecto privado — Ferretería Las F © 2026
