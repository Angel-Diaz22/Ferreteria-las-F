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
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->string('type', 20)->default('counter')->after('name');
            $table->boolean('is_main')->default(false)->after('type');
            $table->integer('display_order')->default(0)->after('is_main');
            $table->text('description')->nullable()->after('display_order');
            $table->index('type');
            $table->index('is_main');
        });

        // Actualizar registros existentes para compatibilidad inmediata
        DB::table('cash_registers')
            ->where('name', 'like', '%Caja 1%')
            ->update(['type' => 'counter', 'is_main' => false, 'display_order' => 1]);

        DB::table('cash_registers')
            ->where('name', 'like', '%Caja 2%')
            ->update(['type' => 'counter', 'is_main' => false, 'display_order' => 2]);

        DB::table('cash_registers')
            ->where(function ($query) {
                $query->where('name', 'like', '%Caja 3%')
                    ->orWhere('name', 'like', '%Central%')
                    ->orWhere('name', 'like', '%Cobro%')
                    ->orWhere('name', 'like', '%Patio%');
            })
            ->update(['type' => 'cashier', 'is_main' => true, 'display_order' => 3]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['is_main']);
            $table->dropColumn(['type', 'is_main', 'display_order', 'description']);
        });
    }
};
