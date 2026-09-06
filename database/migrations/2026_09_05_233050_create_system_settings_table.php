<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // boolean, float, integer, string, json
            $table->string('group')->default('general');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Configuración inicial: Ferretería Las F NO es responsable de IVA
        DB::table('system_settings')->insert([
            [
                'key' => 'iva_enabled',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'tax',
                'description' => 'Determina si se calcula y muestra el IVA en ventas y POS',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'iva_percentage',
                'value' => '19.00',
                'type' => 'float',
                'group' => 'tax',
                'description' => 'Porcentaje de IVA aplicable cuando el IVA está habilitado',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'company_name',
                'value' => 'FERRETERIA LAS F',
                'type' => 'string',
                'group' => 'company',
                'description' => 'Nombre o razón social de la ferretería',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'company_nit',
                'value' => '7719126',
                'type' => 'string',
                'group' => 'company',
                'description' => 'NIT o identificación tributaria',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'company_regime',
                'value' => 'NO RESPONSABLE DE IVA',
                'type' => 'string',
                'group' => 'company',
                'description' => 'Régimen tributario DIAN',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'company_address',
                'value' => 'CALLE 21 N. 56-74',
                'type' => 'string',
                'group' => 'company',
                'description' => 'Dirección del establecimiento',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'company_phone',
                'value' => '3142103651 Y 3053198658',
                'type' => 'string',
                'group' => 'company',
                'description' => 'Teléfonos de contacto',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
