<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * MIGRACIÓN: Hacer nullable el campo 'document' en clientes
 * ============================================================================
 * ¿POR QUÉ NECESITAMOS ESTO?
 *
 * En el flujo de "Creación Rápida de Cliente" desde el POS, el cajero solo
 * necesita capturar el NOMBRE. El documento (CC, NIT, etc.) es información
 * que se puede completar después desde el módulo de Clientes completo.
 *
 * Sin esta migración, la base de datos rechazaría el INSERT porque 'document'
 * tiene una restricción NOT NULL sin valor por defecto, lanzando un error
 * de violación de constraint.
 *
 * La decisión de hacerlo nullable es correcta porque:
 * 1. Un cliente de mostrador frecuente puede comenzar sin documento y luego actualizar.
 * 2. El módulo completo de Clientes (Filament Resource) sigue pidiendo el documento.
 * 3. Solo la creación rápida del POS puede omitirlo.
 */
return new class extends Migration
{
    /**
     * Ejecutar la migración (aplicar cambio).
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // nullable() permite insertar NULL en esta columna sin error de BD
            $table->string('document')->nullable()->change();
        });
    }

    /**
     * Revertir la migración (deshacer cambio, volver al estado anterior).
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Al revertir, restauramos la restricción NOT NULL
            // Nota: si hay registros con document=NULL, este rollback fallará
            $table->string('document')->nullable(false)->change();
        });
    }
};
