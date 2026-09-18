<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    use HasFactory;

    public const TYPE_COUNTER = 'counter';

    public const TYPE_CASHIER = 'cashier';

    public const TYPE_HYBRID = 'hybrid';

    protected $fillable = [
        'warehouse_id',
        'name',
        'type',
        'is_main',
        'display_order',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_main' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CashRegister $register) {
            if (static::count() >= 10) {
                throw new \DomainException('Se ha alcanzado el límite máximo de 10 cajas registradoras en el sistema.');
            }

            if (! isset($register->attributes['type'])) {
                $lower = strtolower($register->name ?? '');
                if (str_contains($lower, 'caja 3') || str_contains($lower, 'central') || str_contains($lower, 'cobro') || str_contains($lower, 'patio')) {
                    $register->type = self::TYPE_CASHIER;
                    $register->is_main = true;
                } else {
                    $register->type = self::TYPE_COUNTER;
                }
            }
        });

        static::saving(function (CashRegister $register) {
            if ($register->is_main) {
                static::where('id', '!=', $register->id ?? 0)
                    ->where('is_main', true)
                    ->update(['is_main' => false]);
            }
        });
    }

    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_COUNTER => 'Mostrador (Atención / Pedidos sin dinero)',
            self::TYPE_CASHIER => 'Recaudadora (Caja Central / Cobro y Arqueo)',
            self::TYPE_HYBRID => 'Híbrida (Atención y Cobro Directo)',
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
     * Determina si la caja es de rol exclusivo recaudadora (Caja Central / Pagos).
     */
    public function isCashier(): bool
    {
        if ($this->type !== null) {
            return $this->type === self::TYPE_CASHIER;
        }

        $lower = strtolower($this->name ?? '');

        return str_contains($lower, 'caja 3')
            || str_contains($lower, 'central')
            || str_contains($lower, 'cobro')
            || str_contains($lower, 'patio');
    }

    /**
     * Determina si la caja maneja dinero físico / efectivo.
     * Recaudadoras e Híbridas manejan dinero y requieren arqueo.
     */
    public function handlesCash(): bool
    {
        if ($this->type !== null) {
            return in_array($this->type, [self::TYPE_CASHIER, self::TYPE_HYBRID], true);
        }

        return $this->isCashier();
    }

    /**
     * Determina si es una caja de atención de mostrador (Mostrador e Híbrida).
     */
    public function isAttentionRegister(): bool
    {
        if ($this->type !== null) {
            return in_array($this->type, [self::TYPE_COUNTER, self::TYPE_HYBRID], true);
        }

        return ! $this->isCashier();
    }

    /**
     * Determina si es una caja híbrida.
     */
    public function isHybrid(): bool
    {
        return $this->type === self::TYPE_HYBRID;
    }

    /**
     * Determina si es la caja principal asignada.
     */
    public function isMain(): bool
    {
        return (bool) $this->is_main;
    }
}
