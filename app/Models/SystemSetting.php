<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * Obtener el valor de una configuración casteado a su tipo.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (! $setting || is_null($setting->value)) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'float' => (float) $setting->value,
            'integer' => (int) $setting->value,
            'json' => json_decode($setting->value, true),
            default => (string) $setting->value,
        };
    }

    /**
     * Guardar o actualizar una configuración.
     */
    public static function set(string $key, mixed $value, ?string $type = null, string $group = 'general'): void
    {
        $stringValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        if (is_array($value) || is_object($value)) {
            $stringValue = json_encode($value);
            $type = $type ?? 'json';
        }

        if (is_null($type)) {
            if (is_bool($value)) {
                $type = 'boolean';
            } elseif (is_float($value)) {
                $type = 'float';
            } elseif (is_int($value)) {
                $type = 'integer';
            } else {
                $type = 'string';
            }
        }

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $stringValue,
                'type' => $type,
                'group' => $group,
            ]
        );
    }

    /**
     * Indica si el cálculo y visualización de IVA están activos en el sistema.
     */
    public static function isIvaEnabled(): bool
    {
        return (bool) static::get('iva_enabled', true);
    }

    /**
     * Retorna el porcentaje de IVA por defecto configurado.
     */
    public static function getIvaRate(): float
    {
        return (float) static::get('iva_percentage', 19.00);
    }
}
