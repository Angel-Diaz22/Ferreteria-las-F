<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * ============================================================================
 * MODELO: Customer (Cliente de la Ferretería)
 * ============================================================================
 * Maneja datos de identificación, tarifas asignadas, límites de crédito
 * y cumplimiento de la Ley 1581 de 2012 (Habeas Data).
 */
class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'price_list_id',
        'name',
        'document_type',
        'document',
        'phone',
        'email',
        'address',
        'city',
        'credit_limit',
        'current_debt',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'current_debt' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * RELACIÓN POLIMÓRFICA SIMULADA: Registros de consentimiento de datos personales
     */
    public function consentLogs(): HasMany
    {
        return $this->hasMany(ConsentLog::class, 'subject_id')->where('subject_type', 'customer');
    }

    public function latestConsentLog(): HasOne
    {
        return $this->hasOne(ConsentLog::class, 'subject_id')
            ->where('subject_type', 'customer')
            ->latestOfMany('consented_at');
    }

    /**
     * LÓGICA DE NEGOCIO: Cupo disponible para nuevas compras fiadas
     */
    public function getAvailableCreditAttribute(): float
    {
        return max(0.0, (float) ($this->credit_limit - $this->current_debt));
    }

    /**
     * Verifica si el cliente tiene cupo suficiente para un monto determinado
     */
    public function hasAvailableCredit(float $amount): bool
    {
        return ($this->current_debt + $amount) <= $this->credit_limit;
    }

    /**
     * Verifica si el cliente ya otorgó autorización de Habeas Data
     */
    public function getHasConsentedAttribute(): bool
    {
        if (array_key_exists('has_consented', $this->attributes)) {
            return (bool) $this->attributes['has_consented'];
        }

        if (array_key_exists('consent_logs_exists', $this->attributes)) {
            return (bool) $this->attributes['consent_logs_exists'];
        }

        return $this->consentLogs()->exists();
    }
}
