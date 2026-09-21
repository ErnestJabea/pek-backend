<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
        'type',
    ];

    /**
     * Obtenir la valeur d'une configuration par sa clé avec gestion du cache.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("system_setting.{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            if (! $setting) {
                return $default;
            }

            return match ($setting->type) {
                'integer', 'int' => (int) $setting->value,
                'boolean', 'bool' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'array' => json_decode((string) $setting->value, true) ?? [],
                default => (string) $setting->value,
            };
        });
    }

    /**
     * Mettre à jour ou créer une configuration.
     */
    public static function set(string $key, mixed $value, ?string $type = null, ?string $description = null): static
    {
        $stringValue = is_array($value) ? json_encode($value) : (string) $value;

        $setting = static::updateOrCreate(
            ['key' => $key],
            array_filter([
                'value' => $stringValue,
                'type' => $type,
                'description' => $description,
            ], fn ($val) => $val !== null)
        );

        Cache::forget("system_setting.{$key}");

        return $setting;
    }

    /**
     * Vider le cache de toutes les configurations.
     */
    public static function clearCache(): void
    {
        $keys = static::pluck('key');
        foreach ($keys as $key) {
            Cache::forget("system_setting.{$key}");
        }
    }
}
