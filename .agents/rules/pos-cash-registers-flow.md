---
trigger: model_decision
description: Regla de negocio del flujo dinámico de cajas POS en Ferretería Las F (hasta 10 cajas, tipos Mostrador, Recaudadora e Híbrida, y asignación de Caja Principal).
---

# Regla de Negocio: Gestión y Operación Dinámica de Cajas (POS)

## 1. Tipos de Caja y Roles Operativos:
- **Mostrador (`counter`)**:
  - Puestos de atención y cotización para asesores de venta.
  - No manejan dinero físico, efectivo, tarjetas ni gaveta de dinero.
  - La apertura de turno se realiza automáticamente con base inicial = $0 COP.
  - Prohibido el acceso a la Pestaña 2 (Módulo de Confirmación de Pagos / Caja Central).
  - En el carrito de ventas, su acción es "GENERAR PEDIDO E IMPRIMIR TIRILLA (PASO 1)".
  - El cierre de turno es una liberación directa del puesto sin arqueo en efectivo.
- **Recaudadora (`cashier`)**:
  - Exclusiva para cobro, recepción de pagos (efectivo, tarjeta, transferencia, crédito) y asentamiento contable.
  - Acceso a la Pestaña 2 para validar pedidos en cola y estampar sello "PAGADO".
  - Maneja dinero físico, base inicial en efectivo y arqueo contable obligatorio al cierre.
  - Acceso restringido exclusivamente al Administrador o personal autorizado.
- **Híbrida (`hybrid`)**:
  - Combina atención y cobro directo en el mismo puesto (para terminales autónomas).
  - Maneja dinero físico, base inicial y arqueo al cierre de turno.

## 2. Límites y Gestión Administrativa:
- **Límite del Sistema**: Máximo 10 cajas registradoras configuradas.
- **Gestión Exclusiva**: Solo el usuario con rol `admin` puede crear, editar, activar, desactivar o eliminar cajas desde `Configuración > Cajas Registradoras`.
- **Independencia de Bodega**: Las cajas pueden asignarse opcionalmente a una bodega o existir de manera independiente.
- **Protección de Datos**: No se permite eliminar cajas que tengan turnos o registros contables asociados (deben desactivarse).

## 3. Asignación y Selección de Terminales:
- **Caja Principal (`is_main`)**: Solo una caja del sistema puede ser la principal. El Administrador ingresa automáticamente a esta caja al cargar el POS.
- **Exclusión Mutua**: Dos usuarios no pueden operar la misma caja de forma simultánea.
- **Visibilidad**: Los asesores/cajeros sólo ven los puestos de atención (`counter` e `hybrid`) activos. Las cajas exclusivamente recaudadoras (`cashier`) están bloqueadas para usuarios no-administradores.
