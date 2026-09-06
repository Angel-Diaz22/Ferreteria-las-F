<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(CashShift::class);
    }

    /**
     * Determina si la caja es la Caja Central / Recaudadora (Caja 3).
     */
    public function isCashier(): bool
    {
        $lower = strtolower($this->name);

        return str_contains($lower, 'caja 3')
            || str_contains($lower, 'central')
            || str_contains($lower, 'cobro')
            || str_contains($lower, 'patio');
    }

    /**
     * Determina si la caja maneja dinero físico / efectivo.
     * Solo la Caja 3 maneja dinero. Las Cajas 1 y 2 son exclusivamente de atención/mostrador.
     */
    public function handlesCash(): bool
    {
        return $this->isCashier();
    }

    /**
     * Determina si es una caja de atención de mostrador (Cajas 1 y 2).
     */
    public function isAttentionRegister(): bool
    {
        return ! $this->isCashier();
    }
}
