# Original User Request

## 2026-09-06T03:32:58Z

Auditoría integral, depuración profunda de código muerto, endurecimiento de seguridad y optimización de rendimiento de todos los recursos y CRUDs del sistema ERP/POS Ferretería Las F (Laravel 12 / PHP 8.4 / Filament v3 / Livewire).

Working directory: /Users/angeldiaz/Documents/FerreterialasF
Integrity mode: development

## Requirements

### R1. Auditoría Integral y Corrección de Recursos CRUD en Filament
Examinar exhaustivamente todos los recursos Filament existentes en `app/Filament/Resources/` (Productos, Categorías, Marcas, Bodegas, Proveedores, Clientes, Listas de Precios, Compras, Ventas, Cotizaciones, Cajas, Turnos y Movimientos de Caja). Asegurar que la creación, edición, visualización, filtrado, paginación y eliminación de registros funcionen de manera consistente, sin excepciones en tiempo de ejecución, con validaciones robustas y optimización de consultas Eloquent (eliminando problemas de N+1 mediante eager loading de relaciones).

### R2. Detección y Eliminación Segura de Código Muerto
Identificar y suprimir de forma segura clases, métodos huérfanos, rutas sin uso, componentes Livewire descontinuados o vistas Blade redundantes que hayan quedado tras iteraciones de desarrollo, preservando rigurosamente toda la lógica y datos del negocio.

### R3. Validación de Seguridad, Concurrencia e Integridad Transaccional
Verificar que los flujos críticos (movimientos de inventario, cierres y aperturas de caja, pagos del POS, ventas directas y cotizaciones) utilicen transacciones de base de datos (`DB::transaction`), bloqueos pesimistas (`lockForUpdate`) donde exista riesgo de condiciones de carrera, y comprobaciones de autorización por roles y permisos antes de ejecutar acciones sensibles.

### R4. Modernización y Aprovechamiento de Laravel & Filament v3
Aprovechar al máximo las capacidades nativas que ofrece Laravel y Filament v3:
- Uso óptimo de Enums de PHP 8.4 y Form Requests/Casts.
- Implementación de Table Actions, Bulk Actions y Modales nativos de Filament.
- Estandarización de badges de estado, formatos monetarios institucionales y notificaciones.
- Respetar la regla de negocio documentada en `.agents/rules/pos-cash-registers-flow.md` y `.ai/rules/boost/filament-tailwind-css-grid.md`.

## Verification Resources

- Suite de pruebas de Pest: `"$HOME/Library/Application Support/Herd/bin/php" artisan test --compact`
- Formateador de código Pint: `"$HOME/Library/Application Support/Herd/bin/php" vendor/bin/pint --dirty --format agent`
- Verificación de rutas de la aplicación: `"$HOME/Library/Application Support/Herd/bin/php" artisan route:list`
- Verificación de tipos y linter estático del framework.

## Acceptance Criteria

### Integridad Funcional
- [ ] Todos los recursos Filament cargan sin errores ni advertencias y permiten crear, editar y consultar registros sin fallos 500.
- [ ] La suite de pruebas de Pest pasa al 100% de manera determinista (`php artisan test`).
- [ ] Se mantienen intactas las reglas de negocio del flujo de cajas (Cajas 1 y 2 de mostrador sin dinero ni cobros, y Caja 3 para Gerencia/Cobros).

### Calidad de Código y Limpieza
- [ ] El código muerto y componentes obsoletos han sido removidos sin romper ninguna funcionalidad activa.
- [ ] Ninguna consulta en tablas de Filament o en el POS genera consultas N+1 en peticiones de listado.
- [ ] Laravel Pint valida exitosamente el estilo del código sin errores de sintaxis o formato.
