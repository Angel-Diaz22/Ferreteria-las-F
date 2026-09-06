<style>
    /* =====================================================================
     * PERSONALIZACIÓN DEL SIDEBAR (BARRA LATERAL) - FERRETERÍA LAS F
     * =====================================================================
     * Este archivo inyecta estilos CSS para:
     * 1. Darle color y personalidad a la barra lateral (naranja + blanco cálido).
     * 2. Efecto HOVER interactivo: al pasar el mouse, el módulo se ilumina,
     *    se desliza hacia la derecha y muestra una línea naranja marcadora.
     * 3. Módulo activo (página actual): botón naranja sólido con sombra suave.
     */

    /* Contenedor principal del Sidebar */
    .fi-sidebar {
        background-color: #f8fafc !important;
        border-right: 2px solid #fed7aa !important; /* Borde sutil naranja/cálido */
        transition: width 0.25s ease-in-out !important;
    }

    /* Cabecera del logo en el sidebar */
    .fi-sidebar-header {
        background-color: #ffffff !important;
        border-bottom: 1px solid #e2e8f0 !important;
        padding-top: 1rem !important;
        padding-bottom: 1rem !important;
    }

    /* Títulos de los Grupos (Ventas y Clientes, Catálogo, Compras...) */
    .fi-sidebar-group-label {
        color: #ea580c !important; /* Naranja corporativo */
        font-weight: 800 !important;
        font-size: 0.72rem !important;
        text-transform: uppercase !important;
        letter-spacing: 0.08em !important;
        padding-top: 0.75rem !important;
    }

    /* Botones/Enlaces de cada Módulo */
    .fi-sidebar-item-button {
        border-radius: 0.65rem !important;
        margin: 2px 0.5rem !important;
        padding: 0.6rem 0.75rem !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
        position: relative !important;
        border-left: 3px solid transparent !important;
    }

    /* =====================================================================
     * EFECTO HOVER: SE MARCA AL PASAR EL MOUSE POR ENCIMA
     * ===================================================================== */
    .fi-sidebar-item-button:hover {
        background-color: #fff7ed !important; /* Fondo naranja muy suave */
        border-left: 3px solid #ea580c !important; /* Línea marcadora naranja */
        transform: translateX(4px) !important; /* Desplazamiento interactivo */
        box-shadow: 0 2px 8px -2px rgba(234, 88, 12, 0.2) !important;
    }

    .fi-sidebar-item-button:hover .fi-sidebar-item-label {
        color: #c2410c !important; /* Texto naranja oscuro */
        font-weight: 700 !important;
    }

    .fi-sidebar-item-button:hover .fi-sidebar-item-icon {
        color: #ea580c !important; /* Ícono naranja */
        transform: scale(1.15) !important; /* Crece ligeramente */
        transition: transform 0.2s ease !important;
    }

    /* =====================================================================
     * MÓDULO ACTIVO: LA PÁGINA EN LA QUE TE ENCUENTRAS
     * ===================================================================== */
    .fi-sidebar-item.fi-sidebar-item-active .fi-sidebar-item-button,
    .fi-sidebar-item-button[aria-current="page"] {
        background: linear-gradient(135deg, #ea580c 0%, #f97316 100%) !important;
        color: #ffffff !important;
        border-left: 3px solid #9a3412 !important;
        box-shadow: 0 4px 12px -2px rgba(234, 88, 12, 0.4) !important;
        transform: translateX(2px) !important;
    }

    .fi-sidebar-item.fi-sidebar-item-active .fi-sidebar-item-label,
    .fi-sidebar-item.fi-sidebar-item-active .fi-sidebar-item-icon,
    .fi-sidebar-item-button[aria-current="page"] .fi-sidebar-item-label,
    .fi-sidebar-item-button[aria-current="page"] .fi-sidebar-item-icon {
        color: #ffffff !important;
        font-weight: 800 !important;
    }
</style>
