---
trigger: model_decision
description: Regla de negocio del flujo de cajas POS en Ferretería Las F (Cajas 1 y 2 de mostrador sin dinero físico ni cobros, y Caja 3 exclusiva de Administración).
---

# Regla de Negocio: Flujo de Cajas de Atención vs Caja Central (POS)

## 1. Cajas 1 y 2 (Mostrador / Asesores de Venta):
- No manejan dinero físico, efectivo, tarjetas ni gaveta de dinero.
- La apertura de turno se realiza automáticamente con base inicial = $0 COP (no se muestra ni se pide campo de base inicial).
- Tienen prohibido el acceso a la Pestaña 2 (Módulo de Confirmación de Pago / Caja Central).
- En el carrito de ventas, su única acción es "GENERAR PEDIDO E IMPRIMIR TIRILLA (PASO 1)"; no tienen opción de cobro directo en mostrador.
- El cierre de turno es una liberación directa del puesto, sin cálculo de arqueo en efectivo ni diferencias contables.

## 2. Caja 3 (Caja Central / Administrador):
- Es la única caja recaudadora autorizada para confirmar pagos, recibir dinero (efectivo, tarjeta, transferencia, crédito) y asentar Kardex contable.
- El usuario con rol 'admin' entra automáticamente y por defecto a la Caja 3 al cargar la página del POS (sin modal de selección).
- Tiene acceso a la Pestaña 2 para validar pagos de pedidos en cola y estampar el sello físico "PAGADO".
- Maneja base inicial en efectivo y arqueo contable al cierre de turno.

## 3. Selección de Puestos:
- Los usuarios cajeros/soporte (no administradores) solo ven y pueden seleccionar entre Caja 1 y Caja 2. La Caja 3 está restringida exclusivamente para el Administrador.
