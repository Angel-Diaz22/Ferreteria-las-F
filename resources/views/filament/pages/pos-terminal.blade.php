<x-filament-panels::page>
    {{-- ================================================================= --}}
    {{-- ESTILOS AUTÓNOMOS Y BLINDADOS PARA EL TERMINAL POS                --}}
    {{-- ================================================================= --}}
    {{--
        ¿POR QUÉ DEFINIMOS ESTILOS CSS EXPLÍCITOS AQUÍ?
        Filament v3 tiene su propio paquete de CSS precompilado. Si utilizamos
        clases arbitrarias de Tailwind que no están en el bundle de Filament
        (como bg-green-700, grid-cols-4 o fondos específicos), el navegador
        aplica "text-white" pero sin el fondo, causando que el texto blanco
        se vuelva invisible sobre fondos claros.

        Con estas clases CSS dedicadas garantizamos al 100% que todos los
        colores, modales, botones, grids y textos tengan un contraste perfecto,
        independientemente del proceso de compilación de assets.
    --}}
    <style>
        /* Paleta Institucional Ferretería Las F */
        :root {
            --flf-orange: #ea580c;
            --flf-orange-hover: #c2410c;
            --flf-orange-light: #fff7ed;
            --flf-orange-border: #fed7aa;
            --flf-green: #16a34a;
            --flf-green-dark: #15803d;
            --flf-green-light: #f0fdf4;
            --flf-red: #dc2626;
            --flf-red-light: #fef2f2;
            --flf-dark: #0f172a;
            --flf-gray: #64748b;
            --flf-border: #e2e8f0;
            --flf-bg-card: #ffffff;
        }

        /* Modales Flotantes Centrados */
        .pos-modal-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            background-color: rgba(15, 23, 42, 0.7) !important;
            backdrop-filter: blur(4px) !important;
            z-index: 99999 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 1rem !important;
        }

        .pos-modal-container {
            background-color: #ffffff !important;
            border: 1px solid var(--flf-border) !important;
            border-radius: 1.25rem !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35) !important;
            width: 100% !important;
            max-width: 32rem !important;
            max-height: 90vh !important;
            overflow-y: auto !important;
            color: var(--flf-dark) !important;
        }

        /* Banner de Total a Cobrar en Modal */
        .pos-total-banner {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 100%) !important;
            color: #ffffff !important;
            padding: 1.25rem !important;
            border-radius: 1rem !important;
            text-align: center !important;
            margin-bottom: 1rem !important;
            box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.3) !important;
        }

        .pos-total-banner * {
            color: #ffffff !important;
        }

        /* Grilla de Métodos de Pago (4 Columnas Exactas) */
        .pos-payment-grid {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 0.5rem !important;
            margin-bottom: 1rem !important;
        }

        .pos-payment-btn {
            background-color: #f8fafc !important;
            border: 2px solid var(--flf-border) !important;
            border-radius: 0.75rem !important;
            padding: 0.65rem 0.25rem !important;
            text-align: center !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            color: var(--flf-dark) !important;
            cursor: pointer !important;
            transition: all 0.15s ease !important;
        }

        .pos-payment-btn:hover {
            border-color: var(--flf-orange) !important;
            background-color: var(--flf-orange-light) !important;
        }

        .pos-payment-btn.active {
            border-color: var(--flf-orange) !important;
            background-color: var(--flf-orange-light) !important;
            color: var(--flf-orange-hover) !important;
            box-shadow: 0 0 0 1px var(--flf-orange) !important;
        }

        /* Botón de Confirmación Principal */
        .pos-btn-confirm {
            background-color: #16a34a !important;
            color: #ffffff !important;
            font-weight: 800 !important;
            font-size: 0.95rem !important;
            padding: 0.85rem 1.5rem !important;
            border-radius: 0.75rem !important;
            border: none !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 0.5rem !important;
            box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.4) !important;
            transition: all 0.15s ease !important;
        }

        .pos-btn-confirm:hover:not(:disabled) {
            background-color: #15803d !important;
            transform: translateY(-1px) !important;
        }

        .pos-btn-confirm:disabled {
            opacity: 0.6 !important;
            cursor: not-allowed !important;
        }

        /* Botones Naranja Institucionales */
        .pos-btn-orange {
            background-color: var(--flf-orange) !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            border-radius: 0.75rem !important;
            border: none !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 0.35rem !important;
            transition: all 0.15s ease !important;
        }

        .pos-btn-orange:hover:not(:disabled) {
            background-color: var(--flf-orange-hover) !important;
        }

        /* Botón Cancelar / Secundario */
        .pos-btn-cancel {
            background-color: #f1f5f9 !important;
            color: #334155 !important;
            font-weight: 700 !important;
            font-size: 0.875rem !important;
            padding: 0.85rem 1.25rem !important;
            border-radius: 0.75rem !important;
            border: 1px solid #cbd5e1 !important;
            cursor: pointer !important;
            transition: all 0.15s ease !important;
        }

        .pos-btn-cancel:hover {
            background-color: #e2e8f0 !important;
        }

        /* Formularios y Grupos de Campos en Modales */
        .pos-form-group {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.5rem !important;
            margin-bottom: 1.15rem !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        .pos-form-label-row {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        .pos-form-label {
            font-size: 0.8125rem !important;
            font-weight: 800 !important;
            color: #1e293b !important;
            letter-spacing: -0.01em !important;
        }

        .pos-badge-required {
            background-color: #fef2f2 !important;
            color: #dc2626 !important;
            border: 1px solid #fecaca !important;
            padding: 0.2rem 0.6rem !important;
            border-radius: 9999px !important;
            font-size: 0.7rem !important;
            font-weight: 800 !important;
        }

        .pos-badge-optional {
            background-color: #f1f5f9 !important;
            color: #64748b !important;
            border: 1px solid #e2e8f0 !important;
            padding: 0.2rem 0.55rem !important;
            border-radius: 9999px !important;
            font-size: 0.7rem !important;
            font-weight: 700 !important;
        }

        .pos-input-wrapper {
            display: flex !important;
            align-items: center !important;
            gap: 0.75rem !important;
            padding: 0.65rem 0.95rem !important;
            background-color: #ffffff !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 0.75rem !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
            transition: all 0.2s ease !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        .pos-input-wrapper:focus-within {
            border-color: #ea580c !important;
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15) !important;
        }

        .pos-input-wrapper input {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            background: transparent !important;
            font-size: 0.875rem !important;
            font-weight: 600 !important;
            color: #0f172a !important;
        }

        .pos-input-wrapper input::placeholder {
            color: #94a3b8 !important;
            font-weight: 500 !important;
        }

        .pos-input-icon {
            width: 1.25rem !important;
            height: 1.25rem !important;
            color: #94a3b8 !important;
            flex-shrink: 0 !important;
        }

        .pos-info-alert {
            display: flex !important;
            align-items: flex-start !important;
            gap: 0.75rem !important;
            padding: 0.95rem 1.15rem !important;
            border-radius: 0.85rem !important;
            background-color: #eff6ff !important;
            border: 1px solid #bfdbfe !important;
            color: #1e40af !important;
            font-size: 0.8125rem !important;
            line-height: 1.45 !important;
            margin-top: 0.5rem !important;
            margin-bottom: 0.5rem !important;
            box-sizing: border-box !important;
        }

        .pos-modal-footer {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: 0.85rem !important;
            padding: 1.15rem 1.5rem !important;
            background-color: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            box-sizing: border-box !important;
        }

        /* Badges de Cambio / Vueltas */
        .pos-change-badge {
            background-color: #15803d !important;
            color: #ffffff !important;
            font-weight: 900 !important;
            font-size: 1.15rem !important;
            padding: 0.35rem 0.85rem !important;
            border-radius: 0.5rem !important;
            display: inline-block !important;
        }

        .pos-missing-badge {
            background-color: #dc2626 !important;
            color: #ffffff !important;
            font-weight: 800 !important;
            font-size: 0.9rem !important;
            padding: 0.35rem 0.75rem !important;
            border-radius: 0.5rem !important;
            display: inline-block !important;
        }

        /* Banner de Total en Columna Derecha */
        .pos-cart-total-bar {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 100%) !important;
            color: #ffffff !important;
            padding: 0.75rem 1rem !important;
            border-radius: 0.85rem !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }

        .pos-cart-total-bar * {
            color: #ffffff !important;
        }

        /* Campo de Búsqueda con padding garantizado para el ícono QR */
        .pos-search-input {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
            padding-left: 2.85rem !important;
            padding-right: 1rem !important;
            padding-top: 0.65rem !important;
            padding-bottom: 0.65rem !important;
            font-size: 0.85rem !important;
            line-height: 1.25rem !important;
            background-color: #ffffff !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 0.75rem !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
            transition: all 0.15s ease !important;
            color: #0f172a !important;
        }

        .pos-search-input:focus {
            border-color: #ea580c !important;
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15) !important;
        }

        /* Botones de Categorías con Espacio y Separación Visual */
        .pos-category-pill {
            padding: 0.4rem 0.85rem !important;
            border-radius: 0.65rem !important;
            font-size: 0.725rem !important;
            font-weight: 700 !important;
            white-space: nowrap !important;
            transition: all 0.15s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.35rem !important;
            flex-shrink: 0 !important;
            border: 1.5px solid #e2e8f0 !important;
            background-color: #ffffff !important;
            color: #334155 !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
            cursor: pointer !important;
        }

        .pos-category-pill:hover {
            border-color: #fdba74 !important;
            background-color: #fff7ed !important;
            color: #ea580c !important;
        }

        .pos-category-pill.active {
            background-color: #ea580c !important;
            color: #ffffff !important;
            border-color: #ea580c !important;
            box-shadow: 0 2px 6px rgba(234, 88, 12, 0.35) !important;
        }

        /* Tarjeta de Producto con Ajuste Milimétrico y Cero Desbordamiento */
        .pos-product-card {
            background-color: #ffffff !important;
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 0.85rem !important;
            padding: 0.7rem 0.8rem !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: space-between !important;
            transition: all 0.15s ease !important;
            cursor: pointer !important;
            user-select: none !important;
            min-height: 118px !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04) !important;
        }

        .pos-product-card:hover {
            border-color: var(--flf-orange) !important;
            box-shadow: 0 6px 16px -2px rgba(234, 88, 12, 0.15) !important;
            transform: translateY(-2px) !important;
        }

        .pos-product-card.disabled {
            opacity: 0.45 !important;
            cursor: not-allowed !important;
            pointer-events: none !important;
        }

        /* Billetes Rápidos */
        .pos-quick-bill {
            background-color: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #1e293b !important;
            font-weight: 700 !important;
            font-size: 0.75rem !important;
            padding: 0.3rem 0.6rem !important;
            border-radius: 0.5rem !important;
            cursor: pointer !important;
            transition: all 0.15s ease !important;
        }

        .pos-quick-bill:hover {
            border-color: var(--flf-orange) !important;
            background-color: var(--flf-orange-light) !important;
            color: var(--flf-orange-hover) !important;
        }

        .pos-quick-bill.exact {
            background-color: var(--flf-orange-light) !important;
            border-color: var(--flf-orange) !important;
            color: var(--flf-orange-hover) !important;
        }

        /* ================================================================= */
        /* ESTRUCTURA GENERAL Y ESPACIADO DEL TERMINAL POS                   */
        /* ================================================================= */
        .pos-terminal-wrapper {
            display: flex !important;
            flex-direction: column !important;
            gap: 1.25rem !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        /* Cabecera Unificada (Sustituye las 2 barras apiladas por 1 sola barra limpia) */
        .pos-header-card {
            background-color: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 1rem !important;
            padding: 0.85rem 1.25rem !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 0.75rem !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        .pos-header-main-row {
            display: flex !important;
            flex-wrap: wrap !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 0.85rem !important;
            width: 100% !important;
        }

        .pos-header-left {
            display: flex !important;
            flex-wrap: wrap !important;
            align-items: center !important;
            gap: 0.75rem !important;
        }

        .pos-header-logo {
            width: 2.25rem !important;
            height: 2.25rem !important;
            object-fit: contain !important;
            flex-shrink: 0 !important;
        }

        .pos-bodega-pill {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.4rem !important;
            padding: 0.4rem 0.75rem !important;
            background-color: #fff7ed !important;
            border: 1px solid #fed7aa !important;
            border-radius: 0.6rem !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            color: #9a3412 !important;
            box-sizing: border-box !important;
        }

        .pos-bodega-select {
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            border: none !important;
            background: transparent !important;
            padding: 0 !important;
            margin: 0 !important;
            color: #9a3412 !important;
            cursor: pointer !important;
            outline: none !important;
        }

        .pos-register-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.4rem !important;
            padding: 0.4rem 0.75rem !important;
            background-color: #f8fafc !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 0.6rem !important;
            font-size: 0.75rem !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            line-height: 1.2 !important;
            box-sizing: border-box !important;
        }

        .pos-shift-active-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.4rem !important;
            padding: 0.4rem 0.75rem !important;
            background-color: #f0fdf4 !important;
            border: 1px solid #bbf7d0 !important;
            border-radius: 0.6rem !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            color: #15803d !important;
            line-height: 1.2 !important;
            box-sizing: border-box !important;
        }

        .pos-status-dot-green {
            width: 0.5rem !important;
            height: 0.5rem !important;
            border-radius: 9999px !important;
            background-color: #22c55e !important;
            display: inline-block !important;
            flex-shrink: 0 !important;
        }

        .pos-no-register-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.4rem !important;
            padding: 0.4rem 0.75rem !important;
            background-color: #fef2f2 !important;
            border: 1px solid #fecaca !important;
            border-radius: 0.6rem !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            color: #dc2626 !important;
            line-height: 1.2 !important;
            box-sizing: border-box !important;
        }

        .pos-header-right {
            display: flex !important;
            flex-wrap: wrap !important;
            align-items: center !important;
            gap: 0.75rem !important;
        }

        .pos-user-pill {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.4rem !important;
            padding: 0.4rem 0.75rem !important;
            background-color: #f1f5f9 !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 0.6rem !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            color: #334155 !important;
            line-height: 1.2 !important;
            box-sizing: border-box !important;
        }

        .pos-btn-shift-close {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.4rem !important;
            padding: 0.4rem 0.85rem !important;
            background-color: #f8fafc !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 0.6rem !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            color: #475569 !important;
            cursor: pointer !important;
            transition: all 0.15s ease !important;
            box-sizing: border-box !important;
        }

        .pos-btn-shift-close:hover {
            background-color: #fef2f2 !important;
            border-color: #fecaca !important;
            color: #dc2626 !important;
        }

        .pos-btn-shift-open {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.4rem !important;
            padding: 0.4rem 0.85rem !important;
            background-color: #ea580c !important;
            border: 1px solid #ea580c !important;
            border-radius: 0.6rem !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            cursor: pointer !important;
            transition: all 0.15s ease !important;
            box-sizing: border-box !important;
        }

        .pos-btn-shift-open:hover {
            background-color: #c2410c !important;
        }

        .pos-sales-history-link {
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.35rem !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            color: #ea580c !important;
            text-decoration: none !important;
            padding: 0.4rem 0.65rem !important;
            border-radius: 0.6rem !important;
            transition: all 0.15s ease !important;
            box-sizing: border-box !important;
        }

        .pos-sales-history-link:hover {
            background-color: #fff7ed !important;
            color: #c2410c !important;
        }

        .pos-header-subrow {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            font-size: 0.75rem !important;
            color: #64748b !important;
            border-top: 1px solid #f1f5f9 !important;
            padding-top: 0.6rem !important;
            margin-top: 0.1rem !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        /* Selector de Fases / Pestañas */
        .pos-phase-tabs-container {
            display: flex !important;
            align-items: center !important;
            gap: 0.65rem !important;
            padding: 0.35rem !important;
            background-color: #f1f5f9 !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 0.85rem !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        .pos-phase-tab-btn {
            flex: 1 1 0% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 0.5rem !important;
            padding: 0.65rem 1rem !important;
            border-radius: 0.65rem !important;
            font-weight: 700 !important;
            font-size: 0.825rem !important;
            cursor: pointer !important;
            transition: all 0.15s ease !important;
            box-sizing: border-box !important;
            border: 1.5px solid transparent !important;
            background-color: transparent !important;
            color: #64748b !important;
        }

        .pos-phase-tab-btn:hover:not(.active-terminal):not(.active-caja):not(.active-despacho) {
            background-color: rgba(255, 255, 255, 0.65) !important;
            color: #1e293b !important;
        }

        .pos-phase-tab-btn.active-terminal {
            background-color: #ffffff !important;
            color: #ea580c !important;
            border-color: #fed7aa !important;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06) !important;
        }

        .pos-phase-tab-btn.active-caja {
            background-color: #ffffff !important;
            color: #15803d !important;
            border-color: #bbf7d0 !important;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06) !important;
        }

        .pos-phase-tab-btn.active-despacho {
            background-color: #ffffff !important;
            color: #0284c7 !important;
            border-color: #bae6fd !important;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06) !important;
        }

        /* Layout Principal: 2 Columnas Blindadas Anti-Desbordamiento */
        .pos-main-grid {
            display: flex !important;
            flex-direction: column !important;
            gap: 1.5rem !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
        }

        .pos-left-column {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.75rem !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
        }

        .pos-search-bar-row {
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
        }

        .pos-categories-row {
            display: flex !important;
            align-items: center !important;
            gap: 0.4rem !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            padding-bottom: 0.35rem !important;
            box-sizing: border-box !important;
            -webkit-overflow-scrolling: touch !important;
        }

        .pos-catalog-grid {
            display: grid !important;
            grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            gap: 0.65rem !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
        }

        @media (min-width: 640px) {
            .pos-catalog-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }
        }

        .pos-cart-panel {
            display: flex !important;
            flex-direction: column !important;
            background-color: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 1rem !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
            overflow: hidden !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
            min-height: 400px !important;
        }

        .pos-tab-content-wrapper {
            display: flex !important;
            flex-direction: column !important;
            gap: 1rem !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        .pos-orders-grid {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        @media (min-width: 768px) {
            .pos-orders-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }

        @media (min-width: 1024px) {
            .pos-orders-grid {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }
    </style>

    <div class="pos-terminal-wrapper">

        {{-- ============================================================= --}}
        {{-- CABECERA UNIFICADA DE TERMINAL POS (ALTA GAMA Y ESPACIADO)    --}}
        {{-- ============================================================= --}}
        <div class="pos-header-card">
            <div class="pos-header-main-row">
                {{-- Bloque Izquierdo: Identidad, Bodega y Estado de Caja --}}
                <div class="pos-header-left">
                    {{-- Logo oficial transparente de Ferretería Las F --}}
                    <img src="{{ asset('images/logo.png') }}" alt="Logo Ferretería Las F" class="pos-header-logo">

                    {{-- Selector de Bodega --}}
                    <div class="pos-bodega-pill">
                        <x-heroicon-o-building-storefront class="w-4 h-4 text-orange-600 shrink-0" />
                        <select wire:model.live="warehouseId" class="pos-bodega-select">
                            @foreach($this->warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Estado de Caja de Atención --}}
                    @if($this->activeCashRegister)
                        <div class="pos-register-badge">
                            <x-heroicon-o-computer-desktop class="w-4 h-4 text-orange-600 shrink-0" />
                            <span>{{ $this->activeCashRegister->name }}</span>
                        </div>
                        <div class="pos-shift-active-badge">
                            <span class="pos-status-dot-green"></span>
                            <span>Turno Activo</span>
                        </div>
                    @else
                        <div class="pos-no-register-badge">
                            <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-red-600 shrink-0" />
                            <span>Sin Caja Asignada</span>
                        </div>
                    @endif
                </div>

                {{-- Bloque Derecho: Usuario, Botón de Turno y Acceso a Historial --}}
                <div class="pos-header-right">
                    {{-- Cajero activo --}}
                    <div class="pos-user-pill">
                        <span class="pos-status-dot-green"></span>
                        <span class="text-gray-700 font-bold">{{ auth()->user()->name }}</span>
                    </div>

                    {{-- Control de Turno / Caja --}}
                    @if($this->activeCashRegister)
                        <button
                            wire:click="openCloseShiftModal"
                            type="button"
                            class="pos-btn-shift-close"
                            title="Cerrar turno de caja y liberar terminal"
                        >
                            <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4 text-gray-500 shrink-0" />
                            <span>Cerrar Turno</span>
                        </button>
                    @else
                        <button
                            wire:click="$set('selectRegisterModalOpen', true)"
                            type="button"
                            class="pos-btn-shift-open"
                        >
                            <x-heroicon-o-arrow-left-on-rectangle class="w-4 h-4 text-white shrink-0" />
                            <span>Seleccionar Caja</span>
                        </button>
                    @endif

                    {{-- Historial de Ventas --}}
                    <a href="{{ url('/admin/sales') }}" class="pos-sales-history-link">
                        <x-heroicon-o-clipboard-document-list class="w-4 h-4 text-orange-600 shrink-0" />
                        <span>Historial de Ventas →</span>
                    </a>
                </div>
            </div>

            {{-- Sublínea de detalle de turno (si hay turno activo) --}}
            @if($this->activeCashShift)
                <div class="pos-header-subrow">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-clock class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                        <span>Turno abierto a las <strong class="text-gray-700">{{ $this->activeCashShift->opened_at?->format('h:i A') }}</strong></span>
                        <span class="text-gray-300">•</span>
                        <span>Base en caja: <strong class="text-gray-700">${{ number_format($this->activeCashShift->opening_amount, 0, ',', '.') }} COP</strong></span>
                    </div>
                    <div class="text-gray-400 hidden sm:block">
                        {{ now()->format('d/m/Y h:i A') }}
                    </div>
                </div>
            @endif
        </div>

        {{-- ============================================================= --}}
        {{-- SELECTOR DE FASES / PESTAÑAS (FLUJO DE 3 PASOS)                --}}
        {{-- ============================================================= --}}
        <div class="pos-phase-tabs-container">
            {{-- Pestaña 1: Terminal / Mostrador (Atención) --}}
            <button
                wire:click="switchTab('terminal')"
                type="button"
                class="pos-phase-tab-btn {{ $currentTab === 'terminal' ? 'active-terminal' : '' }}"
            >
                <x-heroicon-o-shopping-cart class="w-4 h-4 shrink-0" style="{{ $currentTab === 'terminal' ? 'color: #ea580c;' : 'color: #94a3b8;' }}" />
                <span>1. Atención / Mostrador</span>
            </button>

            {{-- Pestaña 2: Caja Central (Gerente / Pagos) - Solo visible para Administrador / Caja 3 --}}
            @if($this->canConfirmPayments)
            <button
                wire:click="switchTab('caja')"
                type="button"
                class="pos-phase-tab-btn {{ $currentTab === 'caja' ? 'active-caja' : '' }}"
            >
                <x-heroicon-o-banknotes class="w-4 h-4 shrink-0" style="{{ $currentTab === 'caja' ? 'color: #15803d;' : 'color: #94a3b8;' }}" />
                <span>2. Caja Central / Cobros</span>
                @if($this->pendingCount > 0)
                    <span class="inline-flex items-center justify-center px-2 py-0.5 text-[11px] font-black rounded-full"
                        style="background-color: #ea580c; color: #ffffff; line-height: 1;">
                        {{ $this->pendingCount }}
                    </span>
                @endif
            </button>
            @endif

            {{-- Pestaña 3: Despacho / Entregas --}}
            <button
                wire:click="switchTab('despacho')"
                type="button"
                class="pos-phase-tab-btn {{ $currentTab === 'despacho' ? 'active-despacho' : '' }}"
            >
                <x-heroicon-o-truck class="w-4 h-4 shrink-0" style="{{ $currentTab === 'despacho' ? 'color: #0284c7;' : 'color: #94a3b8;' }}" />
                <span>3. Despacho / Entregas</span>
                @if($this->paidCount > 0)
                    <span class="inline-flex items-center justify-center px-2 py-0.5 text-[11px] font-black rounded-full"
                        style="background-color: #16a34a; color: #ffffff; line-height: 1;">
                        {{ $this->paidCount }}
                    </span>
                @endif
            </button>
        </div>

        @if($currentTab === 'terminal')
        {{-- ============================================================= --}}
        {{-- LAYOUT PRINCIPAL DEL POS (2 COLUMNAS)                         --}}
        {{-- ============================================================= --}}
        <div class="pos-main-grid">

            {{-- ---------------------------------------------------------  --}}
            {{-- COLUMNA IZQUIERDA (7/12): BÚSQUEDA Y CATÁLOGO DE PRODUCTOS --}}
            {{-- ---------------------------------------------------------  --}}
            <div class="pos-left-column">

                {{-- Barra de Búsqueda y Escáner de Códigos (Padding amplio que previene solapamiento con el QR) --}}
                <div class="pos-search-bar-row">
                    <div class="relative flex-1 min-w-0">
                        <x-heroicon-o-qr-code class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" />
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            wire:keydown.enter="scanBarcode"
                            placeholder="Escanear código de barras, SKU o nombre del producto..."
                            autofocus
                            class="pos-search-input"
                        />
                    </div>
                    <button wire:click="scanBarcode" type="button"
                        class="pos-btn-orange px-4 py-2.5 text-sm font-bold whitespace-nowrap shadow-xs flex items-center gap-1.5 shrink-0">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4 text-white shrink-0" />
                        <span>Buscar</span>
                    </button>
                </div>

                {{-- Filtro de Categorías (Bloques amplios, cómodos y legibles) --}}
                <div class="pos-categories-row scrollbar-thin">
                    <button wire:click="setCategory(null)" type="button"
                        class="pos-category-pill {{ is_null($selectedCategory) ? 'active' : '' }}">
                        <x-heroicon-o-squares-2x2 class="w-4 h-4 shrink-0" />
                        <span>Todas</span>
                    </button>
                    @foreach($this->categories as $cat)
                        <button wire:click="setCategory({{ $cat->id }})" type="button"
                            class="pos-category-pill {{ $selectedCategory === $cat->id ? 'active' : '' }}">
                            <span>{{ $cat->name }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- Grid de Productos del Catálogo (Exactamente 3 Filas con Paginación y Aire Visual) --}}
                <div class="pos-catalog-grid">
                    @forelse($this->products as $product)
                        @php $whStock = (float) ($product->stocks->first()?->current_stock ?? 0); @endphp
                        <div
                            wire:click="{{ $whStock > 0 ? 'addToCart('.$product->id.')' : '' }}"
                            class="pos-product-card {{ $whStock <= 0 ? 'disabled' : '' }}"
                        >
                            {{-- Cabecera: SKU y Badge de Stock con separación generosa --}}
                            <div>
                                <div class="flex items-start justify-between gap-1 mb-1.5">
                                    <span class="text-[10px] font-mono uppercase text-gray-500 font-bold tracking-wider truncate min-w-0">
                                        {{ $product->sku }}
                                    </span>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-md shrink-0 whitespace-nowrap"
                                        style="{{ $whStock > 0 ? 'background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;' : 'background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;' }}">
                                        {{ $whStock > 0 ? number_format($whStock, 0).' '.$product->unit : 'Agotado' }}
                                    </span>
                                </div>

                                {{-- Nombre del Producto con interlineado cómodo y altura mínima uniforme --}}
                                <p class="text-xs font-bold text-gray-900 line-clamp-2 leading-snug" style="min-height: 2rem;">
                                    {{ $product->name }}
                                </p>
                            </div>

                            {{-- Precio de Venta y Botón "+" espaciados con borde suave --}}
                            <div class="mt-2 pt-2 border-t border-gray-100 flex items-center justify-between gap-1">
                                <div class="min-w-0">
                                    <span class="text-sm font-black text-gray-900 tracking-tight">
                                        ${{ number_format($product->sale_price, 0, ',', '.') }}
                                    </span>
                                    <span class="text-[9px] font-semibold text-gray-400">COP</span>
                                </div>
                                <span class="w-6 h-6 flex items-center justify-center rounded-lg shadow-2xs shrink-0 transition-transform active:scale-90"
                                    style="background-color: #fff7ed; color: #ea580c; border: 1px solid #fed7aa;">
                                    <x-heroicon-o-plus class="w-3.5 h-3.5 font-black" />
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-16 text-center text-gray-500 bg-white rounded-xl border border-gray-200">
                            <x-heroicon-o-archive-box-x-mark class="w-10 h-10 mx-auto mb-2 text-gray-300" />
                            <p class="text-sm font-semibold">No se encontraron productos coincidentes.</p>
                        </div>
                    @endforelse
                </div>

                {{-- Barra de Paginación para las 3 Filas --}}
                @if($this->products->hasPages())
                    <div class="flex items-center justify-between px-4 py-3 bg-white border border-gray-200 rounded-xl shadow-xs mt-1">
                        <div class="text-xs text-gray-500 font-medium">
                            Mostrando <strong class="text-gray-800">{{ $this->products->firstItem() }}-{{ $this->products->lastItem() }}</strong> de <strong class="text-gray-800">{{ $this->products->total() }}</strong> artículos
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                wire:click="previousPage"
                                wire:loading.attr="disabled"
                                {{ $this->products->onFirstPage() ? 'disabled' : '' }}
                                type="button"
                                class="px-3.5 py-1.5 text-xs font-bold rounded-lg border transition-all flex items-center gap-1.5
                                    {{ $this->products->onFirstPage() ? 'opacity-40 cursor-not-allowed bg-gray-50 text-gray-400 border-gray-200' : 'bg-white text-gray-700 border-gray-300 hover:bg-orange-50 hover:text-orange-600 hover:border-orange-300' }}">
                                <x-heroicon-s-chevron-left class="w-3.5 h-3.5" />
                                <span>Anterior</span>
                            </button>

                            <span class="px-3 py-1 text-xs font-black text-orange-700 bg-orange-50 rounded-lg border border-orange-200">
                                {{ $this->products->currentPage() }} / {{ $this->products->lastPage() }}
                            </span>

                            <button
                                wire:click="nextPage"
                                wire:loading.attr="disabled"
                                {{ ! $this->products->hasMorePages() ? 'disabled' : '' }}
                                type="button"
                                class="px-3.5 py-1.5 text-xs font-bold rounded-lg border transition-all flex items-center gap-1.5
                                    {{ ! $this->products->hasMorePages() ? 'opacity-40 cursor-not-allowed bg-gray-50 text-gray-400 border-gray-200' : 'bg-white text-gray-700 border-gray-300 hover:bg-orange-50 hover:text-orange-600 hover:border-orange-300' }}">
                                <span>Siguiente</span>
                                <x-heroicon-s-chevron-right class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                @endif

            </div>

            {{-- --------------------------------------------------------- --}}
            {{-- COLUMNA DERECHA (5/12): CLIENTE, CARRITO Y TOTALES        --}}
            {{-- --------------------------------------------------------- --}}
            <div class="pos-cart-panel">

                {{-- Encabezado del Carrito --}}
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                    <div class="flex items-center gap-2.5">
                        <x-heroicon-o-shopping-cart class="w-5 h-5" style="color: #ea580c;" />
                        <span class="text-sm font-black text-gray-900 uppercase tracking-wide">Carrito de Venta</span>
                        @if(count($cart) > 0)
                            <span class="px-2.5 py-0.5 text-[11px] font-black rounded-full text-white" style="background-color: #ea580c;">
                                {{ count($cart) }}
                            </span>
                        @endif
                    </div>
                    <button wire:click="clearCart" type="button"
                        class="text-xs font-bold text-red-600 hover:text-red-800 transition-colors">
                        Vaciar
                    </button>
                </div>

                {{-- Buscador Reactivo de Cliente con Autocompletado y Creación Rápida --}}
                <div class="px-5 pt-3.5 pb-3 border-b border-gray-100 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wide">
                            Cliente Asignado:
                        </label>
                        @if($this->selectedCustomer)
                            <button
                                wire:click="clearCustomer"
                                type="button"
                                class="text-[11px] font-bold text-orange-600 hover:text-orange-800 transition-colors flex items-center gap-1"
                            >
                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                                <span>Cambiar de Cliente</span>
                            </button>
                        @endif
                    </div>

                    @if($this->selectedCustomer)
                        {{-- Tarjeta del Cliente Seleccionado --}}
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 0.85rem; background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); border: 1.5px solid #fed7aa; border-radius: 0.75rem; box-shadow: 0 1px 2px rgba(234, 88, 12, 0.08);">
                            <div style="display: flex; align-items: center; gap: 0.65rem; min-width: 0;">
                                <div style="width: 2.25rem; height: 2.25rem; border-radius: 0.6rem; background-color: #ea580c; color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem; flex-shrink: 0; box-shadow: 0 2px 4px rgba(234, 88, 12, 0.25);">
                                    <x-heroicon-o-user class="w-4 h-4 text-white" />
                                </div>
                                <div style="min-width: 0;">
                                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                                        <p style="font-size: 0.8125rem; font-weight: 900; color: #0f172a; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            {{ $this->selectedCustomer->name }}
                                        </p>
                                        @if($this->selectedCustomer->priceList)
                                            <span style="font-size: 0.65rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 0.4rem; background-color: #fff; color: #c2410c; border: 1px solid #fed7aa; flex-shrink: 0;">
                                                {{ $this->selectedCustomer->priceList->name }}
                                            </span>
                                        @endif
                                    </div>
                                    <p style="font-size: 0.7rem; font-weight: 600; color: #64748b; margin: 0.15rem 0 0 0; display: flex; align-items: center; gap: 0.5rem;">
                                        @if($this->selectedCustomer->phone)
                                            <span>📞 {{ $this->selectedCustomer->phone }}</span>
                                        @endif
                                        @if($this->selectedCustomer->document)
                                            <span>🆔 {{ $this->selectedCustomer->document }}</span>
                                        @endif
                                        <span>• Cupo: <strong style="color: #15803d;">${{ number_format($this->selectedCustomer->available_credit, 0, ',', '.') }}</strong></span>
                                    </p>
                                </div>
                            </div>
                            <button
                                wire:click="clearCustomer"
                                type="button"
                                title="Quitar cliente y volver a mostrador"
                                style="padding: 0.35rem 0.65rem; font-size: 0.75rem; font-weight: 700; color: #dc2626; background-color: #ffffff; border: 1px solid #fecaca; border-radius: 0.5rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.25rem; flex-shrink: 0; transition: all 0.15s ease;"
                                onmouseover="this.style.backgroundColor='#fef2f2';"
                                onmouseout="this.style.backgroundColor='#ffffff';"
                            >
                                <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                                <span>Quitar</span>
                            </button>
                        </div>
                    @else
                        {{-- Buscador con Autocompletado Reactivo --}}
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <div class="flex gap-2">
                                <div class="relative flex-1 min-w-0">
                                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                                    <input
                                        type="text"
                                        wire:model.live.debounce.250ms="customerSearch"
                                        @focus="open = true"
                                        @input="open = true"
                                        placeholder="Buscar por nombre, teléfono o documento..."
                                        class="pos-search-input"
                                        style="padding-left: 2.25rem !important; padding-top: 0.55rem !important; padding-bottom: 0.55rem !important; font-size: 0.8125rem !important;"
                                    />
                                    @if($customerSearch !== '')
                                        <button
                                            wire:click="$set('customerSearch', '')"
                                            type="button"
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-0.5"
                                        >
                                            <x-heroicon-o-x-mark class="w-4 h-4" />
                                        </button>
                                    @endif
                                </div>

                                {{-- Botón Modal Cliente Rápido --}}
                                <button
                                    wire:click="openQuickCustomerModal"
                                    type="button"
                                    title="Crear cliente nuevo rápidamente"
                                    class="pos-btn-orange shrink-0 shadow-xs"
                                    style="width: 2.35rem; height: 2.35rem; padding: 0 !important; border-radius: 0.75rem;"
                                >
                                    <x-heroicon-o-user-plus class="w-4 h-4 text-white" />
                                </button>
                            </div>

                            {{-- Dropdown Flotante con Coincidencias --}}
                            <div
                                x-show="open"
                                x-transition
                                style="position: absolute; left: 0; right: 0; top: 100%; margin-top: 0.35rem; background-color: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 0.75rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1); z-index: 50; max-height: 16rem; overflow-y: auto; display: none;"
                            >
                                {{-- Opción 1: Cliente Mostrador por Defecto --}}
                                <div
                                    @click="open = false"
                                    wire:click="selectCustomer(null)"
                                    style="padding: 0.65rem 0.85rem; border-bottom: 1px solid #f1f5f9; cursor: pointer; display: flex; align-items: center; justify-content: space-between; transition: background 0.15s ease;"
                                    onmouseover="this.style.backgroundColor='#fff7ed';"
                                    onmouseout="this.style.backgroundColor='transparent';"
                                >
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="width: 1.75rem; height: 1.75rem; border-radius: 0.5rem; background-color: #f1f5f9; color: #475569; display: flex; align-items: center; justify-content: center; font-size: 0.75rem;">
                                            🛒
                                        </div>
                                        <div>
                                            <p style="font-size: 0.78rem; font-weight: 800; color: #0f172a; margin: 0;">Cliente Mostrador / Ocasional</p>
                                            <p style="font-size: 0.68rem; font-weight: 600; color: #94a3b8; margin: 0;">Venta sin registrar datos personales</p>
                                        </div>
                                    </div>
                                    <span style="font-size: 0.68rem; font-weight: 700; color: #64748b; background-color: #f1f5f9; padding: 0.15rem 0.45rem; border-radius: 0.35rem;">
                                        Por defecto
                                    </span>
                                </div>

                                {{-- Lista de Clientes Encontrados --}}
                                @forelse($this->filteredCustomers as $cust)
                                    <div
                                        @click="open = false"
                                        wire:click="selectCustomer({{ $cust->id }})"
                                        style="padding: 0.65rem 0.85rem; border-bottom: 1px solid #f8fafc; cursor: pointer; display: flex; align-items: center; justify-content: space-between; transition: background 0.15s ease;"
                                        onmouseover="this.style.backgroundColor='#fff7ed';"
                                        onmouseout="this.style.backgroundColor='transparent';"
                                    >
                                        <div style="min-width: 0;">
                                            <p style="font-size: 0.8125rem; font-weight: 800; color: #0f172a; margin: 0;">{{ $cust->name }}</p>
                                            <p style="font-size: 0.7rem; font-weight: 600; color: #64748b; margin: 0.15rem 0 0 0; display: flex; align-items: center; gap: 0.5rem;">
                                                @if($cust->phone)
                                                    <span>📞 {{ $cust->phone }}</span>
                                                @endif
                                                @if($cust->document)
                                                    <span>🆔 {{ $cust->document }}</span>
                                                @endif
                                            </p>
                                        </div>
                                        <div style="text-align: right; flex-shrink: 0; margin-left: 0.5rem;">
                                            @if($cust->priceList)
                                                <span style="font-size: 0.65rem; font-weight: 800; padding: 0.15rem 0.45rem; border-radius: 0.35rem; background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">
                                                    {{ $cust->priceList->name }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div style="padding: 1rem; text-align: center;">
                                        <p style="font-size: 0.75rem; color: #64748b; font-weight: 600; margin: 0;">
                                            No se encontraron clientes con "{{ $customerSearch }}".
                                        </p>
                                        <button
                                            @click="open = false"
                                            wire:click="openQuickCustomerModal"
                                            type="button"
                                            style="margin-top: 0.5rem; font-size: 0.75rem; font-weight: 800; color: #ea580c; background: transparent; border: none; cursor: pointer; text-decoration: underline;"
                                        >
                                            + Crear nuevo cliente rápidamente
                                        </button>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Lista de Productos en Carrito --}}
                <div class="flex-1 overflow-y-auto px-4 py-1 divide-y divide-gray-100">
                    @forelse($cart as $index => $item)
                        <div class="py-2.5 flex items-center gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-gray-900 truncate leading-tight">
                                    {{ $item['name'] }}
                                </p>
                                <p class="text-[11px] font-medium text-gray-500 mt-0.5">
                                    ${{ number_format($item['unit_price'], 0, ',', '.') }} × {{ (int) $item['quantity'] }}
                                </p>
                            </div>

                            {{-- Botones de Cantidad (+ / -) --}}
                            <div class="flex items-center gap-1 shrink-0">
                                <button wire:click="decrementQuantity({{ $index }})" type="button"
                                    class="w-6 h-6 flex items-center justify-center rounded bg-gray-100 text-gray-800 hover:bg-gray-200 font-black text-sm">
                                    −
                                </button>
                                <span class="w-7 text-center text-xs font-black text-gray-900">
                                    {{ (int) $item['quantity'] }}
                                </span>
                                <button wire:click="incrementQuantity({{ $index }})" type="button"
                                    class="w-6 h-6 flex items-center justify-center rounded bg-gray-100 text-gray-800 hover:bg-gray-200 font-black text-sm">
                                    +
                                </button>
                            </div>

                            {{-- Subtotal y Quitar --}}
                            <div class="w-20 text-right shrink-0">
                                <p class="text-xs font-black text-gray-900">
                                    ${{ number_format($item['subtotal'], 0, ',', '.') }}
                                </p>
                                <button wire:click="removeFromCart({{ $index }})" type="button"
                                    class="text-[10px] font-bold text-red-600 hover:text-red-800">
                                    Quitar
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center text-center py-12 text-gray-400">
                            <x-heroicon-o-shopping-bag class="w-10 h-10 mb-2 text-gray-300" />
                            <p class="text-xs font-bold text-gray-600">Carrito vacío</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Escanea un código o toca un producto</p>
                        </div>
                    @endforelse
                </div>

                {{-- Resumen Financiero y Botón de Cobro --}}
                <div class="px-5 pt-3 pb-4 border-t border-gray-100 space-y-2.5">
                    {{-- Subtotal e IVA --}}
                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 space-y-1 text-xs">
                        <div class="flex justify-between font-semibold text-gray-700">
                            <span>Subtotal:</span>
                            <span class="font-bold text-gray-900">${{ number_format($this->subtotal, 0, ',', '.') }} COP</span>
                        </div>
                        @if($this->isIvaEnabled)
                        <div class="flex justify-between font-semibold text-gray-700">
                            <span>IVA Estimado ({{ number_format(\App\Models\SystemSetting::getIvaRate(), 0) }}%):</span>
                            <span class="font-bold text-gray-900">${{ number_format($this->taxAmount, 0, ',', '.') }} COP</span>
                        </div>
                        @endif
                    </div>

                    {{-- Barra Verde de Total a Pagar --}}
                    <div class="pos-cart-total-bar">
                        <span class="text-xs font-black uppercase tracking-wider text-white">TOTAL:</span>
                        <span class="text-xl font-black text-white">
                            ${{ number_format($this->total, 0, ',', '.') }} <span class="text-xs font-normal">COP</span>
                        </span>
                    </div>

                    {{-- Botón Principal: PASO 1 - Generar Pedido e Imprimir Tirilla --}}
                    <button
                        wire:click="generateOrderAndPrint"
                        wire:loading.attr="disabled"
                        wire:target="generateOrderAndPrint"
                        type="button"
                        {{ empty($cart) ? 'disabled' : '' }}
                        class="pos-btn-orange w-full py-2.5 px-3 text-[13px] font-black shadow-sm flex items-center justify-center gap-1.5"
                    >
                        <span wire:loading.remove wire:target="generateOrderAndPrint" class="flex items-center gap-1.5">
                            <x-heroicon-o-printer class="w-4 h-4 text-white" />
                            GENERAR PEDIDO
                        </span>
                        <span wire:loading wire:target="generateOrderAndPrint" class="flex items-center gap-1.5">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Generando...
                        </span>
                    </button>

                    {{-- Enlace para Cobro Directo en Mostrador (Solo para Administrador / Caja Central) --}}
                    @if($this->canConfirmPayments)
                    <div class="flex items-center justify-between pt-1.5 border-t border-gray-100 mt-1">
                        <span class="text-[10px] text-gray-500 font-medium">¿Venta rápida directa?</span>
                        <button
                            wire:click="openPaymentModal"
                            type="button"
                            {{ empty($cart) ? 'disabled' : '' }}
                            class="text-[11px] font-bold text-green-700 hover:text-green-800 hover:underline flex items-center gap-1 bg-green-50 px-2 py-1 rounded-md border border-green-200 transition-colors"
                        >
                            <x-heroicon-o-banknotes class="w-3.5 h-3.5" />
                            Cobrar Inmediato →
                        </button>
                    </div>
                    @endif
                </div>

            </div>

        </div>
        @endif

        {{-- ============================================================= --}}
        {{-- PESTAÑA 2: CAJA CENTRAL / COBROS (ROL GERENTE)                 --}}
        {{-- ============================================================= --}}
        @if($currentTab === 'caja')
            <div class="pos-tab-content-wrapper">
                {{-- Banner explicativo del rol de Caja Central --}}
                <div class="p-4 rounded-xl flex items-start justify-between gap-4 border"
                    style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-color: #86efac;">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white shrink-0" style="background-color: #15803d;">
                            <x-heroicon-o-banknotes class="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-gray-900">Módulo de Caja Central / Gerencia (Cobros)</h3>
                            <p class="text-xs text-gray-700 mt-0.5">
                                Cuando el cliente presente su tirilla de mostrador, ubique el pedido, registre el pago recibido y <strong>estampe el sello físico "PAGADO" en la tirilla</strong> para que pueda retirar los productos en Despacho.
                            </p>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-xs font-black px-3 py-1.5 rounded-lg inline-block" style="background-color: #15803d; color: #ffffff;">
                            {{ $this->pendingCount }} Pedidos por Cobrar
                        </span>
                    </div>
                </div>

                {{-- Grilla de Pedidos por Cobrar --}}
                <div class="pos-orders-grid">
                    @forelse($this->pendingOrders as $pendingSale)
                        <div class="bg-white border-2 border-gray-200 hover:border-green-500 rounded-xl p-4 flex flex-col justify-between shadow-xs transition-all">
                            <div class="space-y-2.5">
                                {{-- Cabecera de la tarjeta --}}
                                <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                                    <span class="text-sm font-black text-gray-900 flex items-center gap-1.5">
                                        <x-heroicon-o-document-text class="w-4 h-4 text-orange-600" />
                                        {{ $pendingSale->invoice_number }}
                                    </span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md" style="background-color: #fff7ed; color: #ea580c; border: 1px solid #fed7aa;">
                                        ⏳ Esperando Pago
                                    </span>
                                </div>

                                {{-- Info de Cliente y Vendedor --}}
                                <div class="space-y-1 text-xs">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Cliente:</span>
                                        <span class="font-bold text-gray-900">{{ $pendingSale->customer?->name ?? 'Cliente Mostrador / Ocasional' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Atendido por:</span>
                                        <span class="font-semibold text-gray-700">{{ $pendingSale->user?->name ?? 'Vendedor' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Hora:</span>
                                        <span class="text-gray-600">{{ $pendingSale->created_at->format('h:i A') }} ({{ $pendingSale->created_at->diffForHumans() }})</span>
                                    </div>
                                </div>

                                {{-- Detalle de Productos --}}
                                <div class="bg-gray-50 rounded-lg p-2.5 border border-gray-200 text-xs space-y-1 max-h-36 overflow-y-auto">
                                    @foreach($pendingSale->items as $it)
                                        <div class="flex justify-between text-[11px]">
                                            <span class="text-gray-700 truncate mr-2">{{ (int)$it->quantity }}x {{ $it->product?->name }}</span>
                                            <span class="font-bold text-gray-900 shrink-0">${{ number_format($it->subtotal, 0, ',', '.') }}</span>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Total a Cobrar --}}
                                <div class="flex items-center justify-between pt-1">
                                    <span class="text-xs font-black uppercase text-gray-600">Total a Cobrar:</span>
                                    <span class="text-lg font-black text-green-700">
                                        ${{ number_format($pendingSale->total, 0, ',', '.') }} COP
                                    </span>
                                </div>
                            </div>

                            {{-- Botones de Acción --}}
                            <div class="pt-3 border-t border-gray-100 flex gap-2 mt-3">
                                <button
                                    wire:click="cancelPendingOrder({{ $pendingSale->id }})"
                                    wire:confirm="¿Desea anular el pedido {{ $pendingSale->invoice_number }}? Se liberará el stock reservado inmediatamente."
                                    type="button"
                                    class="pos-btn-cancel text-xs py-2 px-3 text-red-600 hover:text-red-800"
                                    title="Anular Pedido y devolver existencias a la bodega"
                                >
                                    Anular
                                </button>
                                <button
                                    wire:click="openCashierPaymentModal({{ $pendingSale->id }})"
                                    type="button"
                                    class="pos-btn-confirm flex-1 py-2 text-xs font-black"
                                >
                                    <x-heroicon-o-banknotes class="w-4 h-4 text-white" />
                                    Cobrar en Caja
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-16 text-center bg-white rounded-xl border border-gray-200">
                            <x-heroicon-o-check-circle class="w-12 h-12 mx-auto text-gray-300 mb-2" />
                            <h4 class="text-sm font-bold text-gray-700">No hay pedidos pendientes de cobro</h4>
                            <p class="text-xs text-gray-500 mt-1">Los pedidos generados en mostrador aparecerán aquí automáticamente para cobro del Gerente.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- ============================================================= --}}
        {{-- PESTAÑA 3: DESPACHO Y ENTREGA (ROL ATENCIÓN / BODEGA)         --}}
        {{-- ============================================================= --}}
        @if($currentTab === 'despacho')
            <div class="pos-tab-content-wrapper">
                {{-- Banner Anti-Fraude de Despacho --}}
                <div class="p-4 rounded-xl flex items-start justify-between gap-4 border"
                    style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-color: #93c5fd;">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white shrink-0" style="background-color: #0284c7;">
                            <x-heroicon-o-shield-check class="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-gray-900">Control de Despacho y Entrega de Mercancía</h3>
                            <p class="text-xs text-gray-700 mt-0.5">
                                🛡️ <strong>DOBLE VALIDACIÓN ANTI-FRAUDE:</strong> Antes de entregar artículos al cliente, verifique que la tirilla física tenga el <strong>sello "PAGADO"</strong> estampado por el Gerente y que el comprobante figure en esta lista de confirmados.
                            </p>
                        </div>
                    </div>
                    <div class="text-right shrink-0">
                        <span class="text-xs font-black px-3 py-1.5 rounded-lg inline-block" style="background-color: #0284c7; color: #ffffff;">
                            {{ $this->paidCount }} Listos para Entrega
                        </span>
                    </div>
                </div>

                {{-- Grilla de Pedidos Listos para Despacho --}}
                <div class="pos-orders-grid">
                    @forelse($this->paidOrders as $paidSale)
                        <div class="bg-white border-2 border-gray-200 hover:border-blue-500 rounded-xl p-4 flex flex-col justify-between shadow-xs transition-all">
                            <div class="space-y-2.5">
                                {{-- Cabecera --}}
                                <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                                    <span class="text-sm font-black text-gray-900 flex items-center gap-1.5">
                                        <x-heroicon-o-document-check class="w-4 h-4 text-green-600" />
                                        {{ $paidSale->invoice_number }}
                                    </span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md" style="background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;">
                                        ✅ Pagado en Caja
                                    </span>
                                </div>

                                {{-- Info del Cliente --}}
                                <div class="space-y-1 text-xs">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Cliente:</span>
                                        <span class="font-bold text-gray-900">{{ $paidSale->customer?->name ?? 'Cliente Mostrador / Ocasional' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Medio de Pago:</span>
                                        <span class="font-bold uppercase text-gray-700">
                                            @switch($paidSale->payment_method)
                                                @case('cash') Efectivo @break
                                                @case('card') Tarjeta @break
                                                @case('credit') Crédito @break
                                                @case('transfer') Transferencia @break
                                                @default {{ $paidSale->payment_method }}
                                            @endswitch
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Total Pagado:</span>
                                        <span class="font-black text-green-700">${{ number_format($paidSale->total, 0, ',', '.') }} COP</span>
                                    </div>
                                </div>

                                {{-- Lista de Artículos a Empacar / Entregar --}}
                                <div class="space-y-1">
                                    <span class="text-[11px] font-black uppercase text-gray-500">Artículos a entregar:</span>
                                    <div class="bg-blue-50/50 rounded-lg p-2.5 border border-blue-100 text-xs space-y-1.5 max-h-40 overflow-y-auto">
                                        @foreach($paidSale->items as $it)
                                            <div class="flex items-center justify-between text-xs font-semibold text-gray-800">
                                                <div class="flex items-center gap-1.5 truncate mr-2">
                                                    <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
                                                    <span class="truncate">{{ $it->product?->name }}</span>
                                                </div>
                                                <span class="font-black text-gray-900 shrink-0 px-2 py-0.5 bg-white rounded border border-gray-200">
                                                    {{ (int)$it->quantity }} {{ $it->product?->unit ?? 'UND' }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            {{-- Botón de Confirmar Entrega --}}
                            <div class="pt-3 border-t border-gray-100 mt-3">
                                <button
                                    wire:click="dispatchOrder({{ $paidSale->id }})"
                                    type="button"
                                    class="pos-btn-orange w-full py-2.5 text-xs font-black shadow-xs"
                                    style="background-color: #0284c7;"
                                >
                                    <x-heroicon-o-truck class="w-4 h-4 text-white" />
                                    Confirmar Entrega / Despachado
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full py-16 text-center bg-white rounded-xl border border-gray-200">
                            <x-heroicon-o-inbox class="w-12 h-12 mx-auto text-gray-300 mb-2" />
                            <h4 class="text-sm font-bold text-gray-700">No hay pedidos pendientes por entregar</h4>
                            <p class="text-xs text-gray-500 mt-1">Los pedidos pagados y sellados por el Gerente aparecerán aquí para entrega física.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

    </div>

    {{-- ================================================================= --}}
    {{-- MODAL 1: CREAR CLIENTE RÁPIDO (REDiseño Amplio y Profesional)      --}}
    {{-- ================================================================= --}}
    @if($quickCustomerModalOpen)
        <div class="pos-modal-overlay">
            <div class="pos-modal-container" style="max-width: 36rem; border-radius: 1.25rem; overflow: hidden;">

                {{-- Cabecera con Degradado Naranja Cálido e Ícono --}}
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.75rem; background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); border-bottom: 1px solid #fed7aa;">
                    <div style="display: flex; align-items: center; gap: 0.85rem;">
                        <div style="width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%); color: #ffffff; box-shadow: 0 2px 4px rgba(234, 88, 12, 0.25); flex-shrink: 0;">
                            <x-heroicon-o-user-plus class="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h3 style="font-size: 1.05rem; font-weight: 900; color: #0f172a; line-height: 1.2; margin: 0;">Creación Rápida de Cliente</h3>
                            <p style="font-size: 0.75rem; font-weight: 600; color: #64748b; margin-top: 0.2rem; margin-bottom: 0;">Registro ágil en mostrador sin frenar la fila de atención</p>
                        </div>
                    </div>
                    <button wire:click="closeQuickCustomerModal" type="button"
                        style="width: 2rem; height: 2rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; border: none; background: transparent; color: #94a3b8; cursor: pointer; transition: all 0.15s ease;"
                        onmouseover="this.style.backgroundColor='#fee2e2'; this.style.color='#dc2626';"
                        onmouseout="this.style.backgroundColor='transparent'; this.style.color='#94a3b8';">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                {{-- Formulario Espacioso con Espaciado Milimétrico --}}
                <div style="padding: 1.75rem 1.75rem 1.25rem 1.75rem;">

                    {{-- 1. Nombre Completo / Razón Social --}}
                    <div class="pos-form-group">
                        <div class="pos-form-label-row">
                            <label class="pos-form-label">
                                Nombre del Cliente / Razón Social
                            </label>
                            <span class="pos-badge-required">
                                * Requerido
                            </span>
                        </div>
                        <div class="pos-input-wrapper">
                            <x-heroicon-o-user class="pos-input-icon" />
                            <input
                                type="text"
                                wire:model.live="quickCustomerName"
                                placeholder="Ej: Inversiones El Roble SAS / Carlos Pérez"
                                maxlength="160"
                                autofocus
                            />
                        </div>
                        @if(isset($quickCustomerErrors['name']))
                            <p style="margin-top: 0.35rem; margin-bottom: 0; font-size: 0.75rem; color: #dc2626; font-weight: 700; display: flex; align-items: center; gap: 0.25rem;">
                                <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                                {{ $quickCustomerErrors['name'][0] }}
                            </p>
                        @endif
                    </div>

                    {{-- 2. Teléfono y Correo Electrónico (2 Columnas con Espacio Amplio) --}}
                    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; margin-bottom: 1.15rem;">
                        {{-- Teléfono --}}
                        <div class="pos-form-group" style="margin-bottom: 0;">
                            <div class="pos-form-label-row">
                                <label class="pos-form-label">
                                    Teléfono
                                </label>
                                <span class="pos-badge-optional">
                                    Opcional
                                </span>
                            </div>
                            <div class="pos-input-wrapper">
                                <x-heroicon-o-phone class="pos-input-icon" />
                                <input
                                    type="tel"
                                    wire:model.live="quickCustomerPhone"
                                    placeholder="Ej: 314 210 3651"
                                    maxlength="20"
                                />
                            </div>
                        </div>

                        {{-- Correo Electrónico --}}
                        <div class="pos-form-group" style="margin-bottom: 0;">
                            <div class="pos-form-label-row">
                                <label class="pos-form-label">
                                    Correo Electrónico
                                </label>
                                <span class="pos-badge-optional">
                                    Opcional
                                </span>
                            </div>
                            <div class="pos-input-wrapper">
                                <x-heroicon-o-envelope class="pos-input-icon" />
                                <input
                                    type="email"
                                    wire:model.live="quickCustomerEmail"
                                    placeholder="Ej: cliente@correo.com"
                                    maxlength="160"
                                />
                            </div>
                            @if(isset($quickCustomerErrors['email']))
                                <p style="margin-top: 0.35rem; margin-bottom: 0; font-size: 0.75rem; color: #dc2626; font-weight: 700; display: flex; align-items: center; gap: 0.25rem;">
                                    <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                                    {{ $quickCustomerErrors['email'][0] }}
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- 3. Tarjeta Informativa con Margen Claro --}}
                    <div class="pos-info-alert">
                        <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 shrink-0" style="margin-top: 0.1rem;" />
                        <div>
                            <strong style="color: #1d4ed8;">Solo el nombre es obligatorio</strong> para vender de inmediato. Podrás completar el resto de sus datos fiscales y cupo de crédito desde el menú de <strong>Clientes</strong> en cualquier momento.
                        </div>
                    </div>
                </div>

                {{-- Pie del Modal con Separación y Botones Organizados --}}
                <div class="pos-modal-footer">
                    <button
                        wire:click="closeQuickCustomerModal"
                        type="button"
                        class="pos-btn-cancel"
                        style="padding: 0.7rem 1.25rem !important;"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="saveQuickCustomer"
                        type="button"
                        class="pos-btn-orange"
                        style="padding: 0.7rem 1.5rem !important; font-size: 0.875rem !important;"
                    >
                        <x-heroicon-o-check class="w-4 h-4 text-white" />
                        <span>Guardar y Asignar al Carrito</span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- ================================================================= --}}
    {{-- MODAL 2: COBRAR VENTA DIRECTA EN MOSTRADOR (1 PASO)               --}}
    {{-- ================================================================= --}}
    @if($paymentModalOpen)
        <div class="pos-modal-overlay">
            <div class="pos-modal-container">

                {{-- Cabecera --}}
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200">
                    <div>
                        <h3 class="text-base font-black text-gray-900">Cobro Inmediato en Mostrador</h3>
                        <p class="text-xs font-semibold text-gray-500">
                            {{ $this->selectedCustomer ? 'Cliente: '.$this->selectedCustomer->name : 'Cliente Mostrador / Ocasional' }}
                        </p>
                    </div>
                    <button wire:click="$set('paymentModalOpen', false)" type="button" class="text-gray-400 hover:text-gray-700">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-5 space-y-3.5">

                    {{-- Banner Verde de Total a Cobrar --}}
                    <div class="pos-total-banner">
                        <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 800; opacity: 0.9;">
                            Total a Cobrar
                        </div>
                        <div style="font-size: 2.25rem; font-weight: 900; line-height: 1.1; margin: 0.25rem 0;">
                            ${{ number_format($this->total, 0, ',', '.') }}
                        </div>
                        <div style="font-size: 0.8rem; font-weight: 700; opacity: 0.9;">
                            Pesos Colombianos (COP)
                        </div>
                    </div>

                    {{-- Grilla de Métodos de Pago --}}
                    <div>
                        <label class="block text-xs font-black text-gray-800 mb-1.5 uppercase tracking-wide">
                            Método de Pago:
                        </label>
                        <div class="pos-payment-grid">
                            @foreach([
                                'cash'     => ['Efectivo', '💵'],
                                'card'     => ['Tarjeta',  '💳'],
                                'transfer' => ['Transf.',  '📲'],
                                'credit'   => ['Crédito',  '📋'],
                            ] as $key => [$label, $icon])
                                <button
                                    wire:click="$set('paymentMethod', '{{ $key }}')"
                                    type="button"
                                    class="pos-payment-btn {{ $paymentMethod === $key ? 'active' : '' }}"
                                >
                                    <div style="font-size: 1.1rem; margin-bottom: 0.15rem;">{{ $icon }}</div>
                                    <div>{{ $label }}</div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Efectivo --}}
                    @if($paymentMethod === 'cash')
                        @php $paid = (float) $paidAmount; @endphp
                        <div class="p-3.5 rounded-xl border border-gray-200 bg-gray-50 space-y-3">
                            <div>
                                <label class="block text-xs font-black text-gray-800 mb-1">
                                    Efectivo Recibido:
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 font-black text-gray-500 text-lg">$</span>
                                    <input
                                        type="number"
                                        wire:model.live="paidAmount"
                                        min="0"
                                        class="w-full pl-7 pr-3 py-2 text-xl font-black text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-400"
                                    />
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-1.5">
                                <button wire:click="setQuickCash({{ $this->total }})" type="button" class="pos-quick-bill exact">
                                    Exacto
                                </button>
                                @foreach([10000, 20000, 50000, 100000, 200000] as $bill)
                                    <button wire:click="setQuickCash({{ $bill }})" type="button" class="pos-quick-bill">
                                        ${{ number_format($bill, 0, ',', '.') }}
                                    </button>
                                @endforeach
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-gray-200">
                                <span class="text-sm font-black text-gray-800">Cambio / Vueltas:</span>
                                @if($paid >= $this->total)
                                    <span class="pos-change-badge">
                                        ${{ number_format($this->changeAmount, 0, ',', '.') }} COP
                                    </span>
                                @else
                                    <span class="pos-missing-badge">
                                        Faltan ${{ number_format($this->total - $paid, 0, ',', '.') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Crédito --}}
                    @if($paymentMethod === 'credit')
                        <div class="p-3.5 rounded-xl border" style="background-color: #fff7ed; border-color: #fed7aa;">
                            @if(! $this->selectedCustomer)
                                <p class="text-xs font-black text-red-600">
                                    ⚠️ Para vender a crédito, selecciona un cliente registrado en el carrito.
                                </p>
                            @else
                                <div class="space-y-1 text-xs text-gray-900">
                                    <div>Cliente: <strong>{{ $this->selectedCustomer->name }}</strong></div>
                                    <div>Cupo Disponible: <strong style="color: #1e40af;">${{ number_format($this->selectedCustomer->available_credit, 0, ',', '.') }} COP</strong></div>
                                    <div class="pt-1 font-black {{ $this->selectedCustomer->hasAvailableCredit($this->total) ? 'text-green-700' : 'text-red-600' }}">
                                        {{ $this->selectedCustomer->hasAvailableCredit($this->total) ? '✅ Cupo suficiente para fiar esta venta' : '❌ Cupo insuficiente para esta venta' }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                </div>

                {{-- Botones de Acción del Modal --}}
                <div class="flex gap-2.5 px-5 pb-5">
                    <button wire:click="$set('paymentModalOpen', false)" type="button" class="pos-btn-cancel w-1/3">
                        Cancelar
                    </button>
                    <button
                        wire:click="processSale"
                        wire:loading.attr="disabled"
                        wire:target="processSale"
                        type="button"
                        class="pos-btn-confirm flex-1"
                    >
                        <span wire:loading.remove wire:target="processSale">
                            ✅ Finalizar Venta
                        </span>
                        <span wire:loading wire:target="processSale">
                            ⏳ Procesando Venta...
                        </span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- ================================================================= --}}
    {{-- MODAL 3: COBRO EN CAJA CENTRAL (PASO 2: GERENTE)                   --}}
    {{-- ================================================================= --}}
    @if($cashierPaymentModalOpen && $selectedSaleForPayment)
        @php
            $saleSubtotal = (float) $selectedSaleForPayment->subtotal;
            $saleTax      = (float) $selectedSaleForPayment->tax_amount;
            $saleTotal    = (float) $selectedSaleForPayment->total;
        @endphp
        <div class="pos-modal-overlay">
            <div class="pos-modal-container" style="max-width: 32rem;">

                {{-- ── Cabecera verde institucional ── --}}
                <div class="flex items-center justify-between px-5 py-4 border-b border-green-200 rounded-t-[1.25rem]"
                    style="background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                            style="background-color: rgba(255,255,255,0.18);">
                            <x-heroicon-o-banknotes class="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-white leading-tight">
                                Cobro — Pedido #{{ $selectedSaleForPayment->invoice_number }}
                            </h3>
                            <p class="text-xs font-medium mt-0.5" style="color: rgba(255,255,255,0.80);">
                                {{ $selectedSaleForPayment->customer?->name ?? 'Cliente Mostrador / Ocasional' }}
                            </p>
                        </div>
                    </div>
                    <button wire:click="closeCashierPaymentModal" type="button"
                        class="text-white/70 hover:text-white transition-colors">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-5 space-y-4">

                    {{-- ── Total a cobrar ── --}}
                    <div class="pos-total-banner">
                        <div style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 800; opacity: 0.85;">
                            Total a Cobrar
                        </div>
                        <div style="font-size: 2.5rem; font-weight: 900; line-height: 1.1; margin: 0.35rem 0;">
                            ${{ number_format($saleTotal, 0, ',', '.') }}
                        </div>
                        <div style="font-size: 0.78rem; font-weight: 700; opacity: 0.85;">
                            Pesos Colombianos (COP)
                        </div>
                    </div>

                    {{-- ── Método de Pago ── --}}
                    <div>
                        <label class="block text-xs font-black text-gray-800 mb-2 uppercase tracking-wide">
                            Método de Pago Recibido
                        </label>
                        <div class="pos-payment-grid">
                            @foreach([
                                'cash'     => ['Efectivo', '💵'],
                                'card'     => ['Tarjeta',  '💳'],
                                'transfer' => ['Transf.',  '📲'],
                                'credit'   => ['Crédito',  '📋'],
                            ] as $key => [$label, $icon])
                                <button
                                    wire:click="$set('cashierPaymentMethod', '{{ $key }}')"
                                    type="button"
                                    class="pos-payment-btn {{ $cashierPaymentMethod === $key ? 'active' : '' }}"
                                >
                                    <div style="font-size: 1.1rem; margin-bottom: 0.15rem;">{{ $icon }}</div>
                                    <div>{{ $label }}</div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- ── Si es Efectivo: Campo de monto y billetes rápidos ── --}}
                    @if($cashierPaymentMethod === 'cash')
                        @php
                            $cashierPaid = (float) $cashierPaidAmount;
                        @endphp
                        <div class="rounded-xl border border-gray-200 bg-gray-50 overflow-hidden">
                            <div class="px-4 py-3 space-y-3">
                                <div>
                                    <label class="block text-xs font-black text-gray-700 mb-1.5">
                                        Efectivo Recibido
                                    </label>
                                    <div class="flex items-center bg-white border border-gray-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-green-500">
                                        <span class="px-3 text-lg font-black text-gray-400 select-none border-r border-gray-200 bg-gray-50">$</span>
                                        <input
                                            type="number"
                                            wire:model.live="cashierPaidAmount"
                                            min="0"
                                            class="flex-1 px-3 py-2.5 text-xl font-black text-gray-900 bg-white border-0 focus:outline-none focus:ring-0"
                                        />
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button wire:click="setCashierQuickCash({{ $saleTotal }})"
                                        type="button" class="pos-quick-bill exact">
                                        Exacto
                                    </button>
                                    @foreach([10000, 20000, 50000, 100000, 200000] as $bill)
                                        <button wire:click="setCashierQuickCash({{ $bill }})"
                                            type="button" class="pos-quick-bill">
                                            ${{ number_format($bill, 0, ',', '.') }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex items-center justify-between px-4 py-2.5 border-t border-gray-200">
                                <span class="text-sm font-black text-gray-800">Cambio / Vueltas:</span>
                                @if($cashierPaid >= $saleTotal)
                                    <span class="pos-change-badge">
                                        ${{ number_format($cashierPaid - $saleTotal, 0, ',', '.') }} COP
                                    </span>
                                @else
                                    <span class="pos-missing-badge">
                                        Faltan ${{ number_format($saleTotal - $cashierPaid, 0, ',', '.') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- ── Aviso de sello físico ── --}}
                    <div class="flex items-start gap-3 p-3.5 rounded-xl border"
                        style="background-color: #fff7ed; border-color: #fed7aa;">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0 mt-0.5" style="color: #c2410c;" />
                        <p class="text-xs font-medium leading-relaxed" style="color: #9a3412;">
                            <strong class="font-black">Sello obligatorio:</strong>
                            Tras confirmar el pago, estampe el sello físico de tinta
                            <strong>"PAGADO"</strong> en la tirilla del cliente para autorizar el despacho.
                        </p>
                    </div>

                </div>

                {{-- ── Pie de acciones ── --}}
                <div class="flex gap-3 px-5 pt-4 pb-5 border-t border-gray-100">
                    <button wire:click="closeCashierPaymentModal" type="button"
                        class="pos-btn-cancel w-2/5 py-3">
                        Cancelar
                    </button>
                    <button
                        wire:click="confirmCashierPayment"
                        wire:loading.attr="disabled"
                        wire:target="confirmCashierPayment"
                        type="button"
                        class="pos-btn-confirm flex-1 py-3"
                    >
                        <span wire:loading.remove wire:target="confirmCashierPayment"
                            class="flex items-center gap-2">
                            <x-heroicon-o-check-circle class="w-5 h-5" />
                            Confirmar Pago y Sellar
                        </span>
                        <span wire:loading wire:target="confirmCashierPayment">
                            Procesando...
                        </span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- ================================================================= --}}
    {{-- MODAL 4: PEDIDO GENERADO (PASO 1: ATENCIÓN)                       --}}
    {{-- ================================================================= --}}
    @if($orderGeneratedModalOpen)
        <div class="pos-modal-overlay">
            <div class="pos-modal-container" style="max-width: 28rem; text-align: center;">
                <div class="py-6 px-4" style="background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%); color: #ffffff;">
                    <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center" style="background-color: rgba(255, 255, 255, 0.2);">
                        <x-heroicon-o-clipboard-document-check class="w-10 h-10 text-white" />
                    </div>
                    <h3 class="text-xl font-black text-white mt-3">¡Pedido Generado!</h3>
                    <p class="text-xs font-bold text-orange-100 mt-1">
                        Comprobante N° <span class="text-white text-base underline">{{ $generatedInvoiceNumber }}</span>
                    </p>
                </div>

                <div class="p-5 space-y-4 text-left">
                    <div class="p-3.5 rounded-xl border border-gray-200 bg-gray-50 space-y-2 text-xs">
                        <div class="flex justify-between font-bold text-gray-800">
                            <span>Total del Pedido:</span>
                            <span class="text-base font-black text-gray-900">${{ number_format($generatedTotal, 0, ',', '.') }} COP</span>
                        </div>
                        <div class="text-[11px] text-gray-500 pt-1 border-t border-gray-200">
                            🔒 El stock de estos artículos ha quedado reservado en bodega.
                        </div>
                    </div>

                    <div class="space-y-2 text-xs text-gray-700 bg-orange-50/60 p-3.5 rounded-xl border border-orange-200">
                        <div class="font-black text-gray-900 text-xs uppercase tracking-wide">Instrucciones de Despacho:</div>
                        <div class="flex items-center gap-2">
                            <span>1️⃣</span>
                            <span>Imprima la <strong>tirilla física</strong> única.</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span>2️⃣</span>
                            <span>Entregue la tirilla al cliente y diríjalo a <strong>Caja Central con el Gerente</strong>.</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span>3️⃣</span>
                            <span>El cliente regresará con la tirilla sellada para retirar sus productos.</span>
                        </div>
                    </div>

                    <button
                        onclick="window.open('/admin/sales/{{ $generatedSaleId }}/receipt', '_blank', 'width=480,height=720,scrollbars=yes')"
                        type="button"
                        class="pos-btn-orange w-full py-3 text-sm font-black shadow-xs"
                    >
                        <x-heroicon-o-printer class="w-4 h-4 text-white" />
                        🖨️ Imprimir Tirilla de Pedido
                    </button>

                    <button
                        wire:click="closeOrderGeneratedModal"
                        type="button"
                        class="pos-btn-cancel w-full py-2.5 text-xs font-bold"
                    >
                        ✨ Tomar Siguiente Pedido
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ================================================================= --}}
    {{-- MODAL 5: VENTA DIRECTA COMPLETADA                                 --}}
    {{-- ================================================================= --}}
    @if($saleSuccessModalOpen)
        <div class="pos-modal-overlay">
            <div class="pos-modal-container" style="max-width: 26rem; text-align: center;">

                {{-- Cabecera Verde con Check --}}
                <div class="py-6 px-4" style="background: linear-gradient(135deg, #15803d, #16a34a); color: #ffffff;">
                    <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center" style="background-color: rgba(255, 255, 255, 0.2);">
                        <x-heroicon-o-check class="w-10 h-10 text-white" />
                    </div>
                    <h3 class="text-xl font-black text-white mt-3">¡Venta Completada!</h3>
                    <p class="text-xs font-bold text-green-100 mt-1">
                        Comprobante N° <span class="text-white text-sm underline">{{ $lastInvoiceNumber }}</span>
                    </p>
                </div>

                {{-- Detalle financiero del pago --}}
                <div class="p-5 space-y-3 text-left">
                    <div class="p-3.5 rounded-xl border border-gray-200 bg-gray-50 space-y-2 text-xs">
                        <div class="flex justify-between font-bold text-gray-800">
                            <span>Total Cobrado:</span>
                            <span class="text-sm font-black text-gray-900">${{ number_format($lastTotal, 0, ',', '.') }} COP</span>
                        </div>
                        @if($lastChange > 0)
                            <div class="flex justify-between items-center font-bold text-gray-800">
                                <span>Vueltas Entregadas:</span>
                                <span class="pos-change-badge" style="font-size: 0.95rem;">
                                    ${{ number_format($lastChange, 0, ',', '.') }} COP
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- Acciones del Ticket --}}
                    <button
                        onclick="window.open('/admin/sales/{{ $lastSaleId }}/receipt', '_blank', 'width=480,height=720,scrollbars=yes')"
                        type="button"
                        class="pos-btn-orange w-full py-3 text-sm font-black shadow-xs"
                    >
                        <x-heroicon-o-printer class="w-4 h-4 text-white" />
                        Imprimir Tirilla Térmica
                    </button>

                    <button
                        wire:click="closeSuccessModal"
                        type="button"
                        class="pos-btn-cancel w-full py-2.5 text-xs font-bold"
                    >
                        ✨ Nueva Venta
                    </button>
                </div>

            </div>
        </div>
    @endif

    {{-- ================================================================= --}}
    {{-- MODAL 6: SELECCIÓN EXCLUSIVA DE CAJA DE ATENCIÓN                  --}}
    {{-- ================================================================= --}}
    @if($selectRegisterModalOpen)
        <div class="pos-modal-overlay">
            <div class="pos-modal-container" style="max-width: 32rem;">
                {{-- Cabecera --}}
                <div class="p-5 border-b border-gray-100 flex items-center justify-between"
                    style="background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%); color: #ffffff;">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white" style="background-color: rgba(255, 255, 255, 0.2);">
                            <x-heroicon-o-computer-desktop class="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h3 class="text-base font-black text-white">Selección de Caja de Atención</h3>
                            <p class="text-xs text-orange-100 mt-0.5">
                                Selecciona el puesto desde donde vas a despachar en este turno.
                            </p>
                        </div>
                    </div>
                    @if($this->activeCashRegister)
                        <button wire:click="$set('selectRegisterModalOpen', false)" type="button" class="text-white hover:opacity-80">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    @endif
                </div>

                {{-- Contenido --}}
                <div class="p-5 space-y-4">
                    {{-- Información de Base Inicial / Tipo de Puesto --}}
                    @if(auth()->user()?->hasRole('admin'))
                        <div class="p-3.5 bg-orange-50 border border-orange-200 rounded-xl">
                            <label class="block text-xs font-bold text-gray-800 mb-1">
                                💵 Base Inicial en Efectivo (Caja Central):
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-black text-gray-500">$</span>
                                <input
                                    type="number"
                                    step="1000"
                                    min="0"
                                    wire:model="shiftOpeningAmount"
                                    placeholder="0"
                                    class="w-full pl-7 pr-3 py-2 text-sm font-bold text-gray-900 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-400"
                                />
                            </div>
                            <p class="text-[11px] text-gray-500 mt-1">Dinero base entregado para vueltas al iniciar el turno.</p>
                        </div>
                    @else
                        <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-2.5">
                            <x-heroicon-o-information-circle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                            <div>
                                <h4 class="text-xs font-black text-amber-900">Puestos de Atención y Mostrador</h4>
                                <p class="text-[11px] text-amber-800 mt-0.5 leading-relaxed">
                                    Estas cajas arman pedidos y apartan inventario. <strong>No manejan dinero en efectivo ni reciben pagos</strong> (la base inicial es automáticamente $0 COP).
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Lista de Cajas Disponibles y en Uso --}}
                    <div class="space-y-2.5">
                        <span class="text-xs font-black text-gray-700 uppercase tracking-wide">Puestos Disponibles:</span>

                        @foreach($this->cashRegistersWithStatus as $item)
                            @php
                                $reg = $item['register'];
                                $status = $item['status'];
                                $operator = $item['operator'];
                                $opened = $item['opened_at'];
                            @endphp

                            <div class="p-4 rounded-xl border-2 transition-all flex items-center justify-between gap-3
                                {{ $status === 'free' ? 'bg-white border-gray-200 hover:border-orange-500 shadow-xs' : '' }}
                                {{ $status === 'mine' ? 'bg-green-50 border-green-400 shadow-xs' : '' }}
                                {{ $status === 'busy' ? 'bg-gray-100 border-gray-200 opacity-80' : '' }}">

                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-xs"
                                        style="{{ $status === 'mine' ? 'background-color: #16a34a; color: #ffffff;' : ($status === 'free' ? 'background-color: #ffedd5; color: #c2410c;' : 'background-color: #e2e8f0; color: #475569;') }}">
                                        {{ substr($reg->name, 0, 6) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <h4 class="text-sm font-black text-gray-900">{{ $reg->name }}</h4>
                                            @if($reg->is_main)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-800">
                                                    ★ Principal
                                                </span>
                                            @endif
                                            @if($reg->type === 'cashier')
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-800">
                                                    Recaudadora
                                                </span>
                                            @elseif($reg->type === 'hybrid')
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800">
                                                    Híbrida
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            @if($status === 'free')
                                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-green-700">
                                                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                                    Disponible
                                                </span>
                                            @elseif($status === 'mine')
                                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-green-800">
                                                    <span class="w-2 h-2 rounded-full bg-green-600 animate-pulse"></span>
                                                    Tu turno actual ({{ $opened }})
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-red-700">
                                                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                                    En uso por {{ $operator }} ({{ $opened }})
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    @if($status === 'free')
                                        <button
                                            wire:click="selectCashRegister({{ $reg->id }})"
                                            type="button"
                                            class="pos-btn-orange px-4 py-2 text-xs font-bold shadow-xs whitespace-nowrap"
                                        >
                                            Entrar a esta Caja
                                        </button>
                                    @elseif($status === 'mine')
                                        <button
                                            wire:click="selectCashRegister({{ $reg->id }})"
                                            type="button"
                                            class="pos-btn-confirm px-4 py-2 text-xs font-bold shadow-xs whitespace-nowrap"
                                        >
                                            Continuar Turno
                                        </button>
                                    @else
                                        {{-- Caja ocupada por otro: Bloqueada --}}
                                        <span class="px-3 py-1.5 text-xs font-bold bg-gray-200 text-gray-500 rounded-lg cursor-not-allowed border border-gray-300">
                                            ⛔ Bloqueada
                                        </span>

                                        @if(auth()->user()?->hasRole('admin'))
                                            <button
                                                wire:click="forceReleaseRegister({{ $reg->id }})"
                                                wire:confirm="¿Estás seguro de liberar forzosamente esta caja? El turno anterior será cerrado."
                                                type="button"
                                                class="px-2 py-1 text-[10px] font-bold text-red-700 bg-red-50 hover:bg-red-100 rounded border border-red-200"
                                                title="Liberar caja abandonada"
                                            >
                                                Liberar (Admin)
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Pie --}}
                <div class="p-4 bg-gray-50 border-t border-gray-200 rounded-b-xl text-center">
                    <p class="text-xs text-gray-500">
                        🔒 <strong>Regla de Terminal Única:</strong> Dos terminales o cajeros no pueden abrir simultáneamente el mismo número de caja.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- ================================================================= --}}
    {{-- MODAL 7: ARQUEO Y CIERRE DE CAJA / LIBERAR                        --}}
    {{-- ================================================================= --}}
    @if($closeShiftModalOpen)
        <div class="pos-modal-overlay">
            <div class="pos-modal-container" style="max-width: 28rem;">
                <div class="p-4 border-b border-gray-200 bg-gray-800 text-white flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-lock-closed class="w-5 h-5 text-orange-400" />
                        <h3 class="text-sm font-black text-white">Arqueo y Cierre de Turno</h3>
                    </div>
                    <button wire:click="$set('closeShiftModalOpen', false)" type="button" class="text-gray-300 hover:text-white">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-5 space-y-4 text-xs">
                    @if($this->activeCashRegister?->handlesCash())
                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-200 space-y-2">
                            <div class="flex justify-between text-gray-600">
                                <span>Caja:</span>
                                <span class="font-bold text-gray-900">{{ $this->activeCashRegister?->name }}</span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Base Inicial:</span>
                                <span class="font-bold text-gray-900">${{ number_format($this->activeCashShift?->opening_amount ?? 0, 0, ',', '.') }} COP</span>
                            </div>
                            <div class="flex justify-between text-gray-600 pt-1 border-t border-gray-200">
                                <span class="font-bold text-gray-800">Total Esperado en Efectivo:</span>
                                <span class="font-black text-sm text-green-700">${{ number_format($this->activeCashShift?->expected_amount ?? 0, 0, ',', '.') }} COP</span>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-gray-800 mb-1">
                                💵 Efectivo Real Contado en Caja ($ COP):
                            </label>
                            <input
                                type="number"
                                step="100"
                                wire:model="shiftClosingAmount"
                                class="w-full px-3 py-2 text-sm font-bold text-gray-900 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500"
                            />
                        </div>
                    @else
                        <div class="p-3.5 bg-orange-50 border border-orange-200 rounded-xl">
                            <h4 class="font-bold text-gray-900 text-sm mb-1">{{ $this->activeCashRegister?->name }}</h4>
                            <p class="text-gray-600 text-[11px] leading-relaxed">
                                Este puesto es de atención de mostrador (sin manejo de dinero físico). Al confirmar, el puesto será liberado inmediatamente para el siguiente turno sin requerir arqueo de efectivo.
                            </p>
                        </div>
                    @endif

                    <div>
                        <label class="block font-bold text-gray-800 mb-1">
                            Observaciones de Entrega de Turno:
                        </label>
                        <textarea
                            wire:model="shiftClosingNotes"
                            rows="2"
                            placeholder="Novedades de caja, billetes rotos, etc."
                            class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg"
                        ></textarea>
                    </div>

                    <div class="pt-2 flex gap-2">
                        <button
                            wire:click="$set('closeShiftModalOpen', false)"
                            type="button"
                            class="pos-btn-cancel flex-1 py-2.5 font-bold"
                        >
                            Cancelar
                        </button>
                        <button
                            wire:click="confirmCloseShift"
                            type="button"
                            class="pos-btn-confirm flex-1 py-2.5 font-bold"
                        >
                            Confirmar y Liberar Caja
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>
