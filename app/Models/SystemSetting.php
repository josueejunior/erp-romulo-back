<?php

namespace App\Models;

use App\Models\Traits\CentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Configurações globais do sistema (credenciais de integração, feature flags,
 * etc.) guardadas fora dos tenants. Valores são criptografados em repouso.
 *
 * Uso:
 *   SystemSetting::get('mercadopago.access_token', env('MP_ACCESS_TOKEN'));
 *   SystemSetting::set('mercadopago.access_token', $token, ['group' => 'mercadopago']);
 *   SystemSetting::getGroup('mercadopago');
 *   SystemSetting::forget('mercadopago.access_token');
 */
class SystemSetting extends Model
{
    use CentralConnection;

    protected $table = 'system_settings';

    protected $fillable = [
        'key',
        'value',
        'group',
        'is_secret',
        'description',
    ];

    protected $casts = [
        'is_secret' => 'boolean',
    ];

    private const CACHE_TTL = 300;
    private const CACHE_PREFIX = 'system_setting:';

    /**
     * Retorna o valor descriptografado (ou $default se ausente).
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cached = Cache::store()->get(self::CACHE_PREFIX . $key);
        if ($cached !== null) {
            return $cached === '__NULL__' ? $default : $cached;
        }

        $row = static::query()->where('key', $key)->first();
        if (!$row || $row->value === null || $row->value === '') {
            Cache::store()->put(self::CACHE_PREFIX . $key, '__NULL__', self::CACHE_TTL);
            return $default;
        }

        try {
            $value = Crypt::decryptString($row->value);
        } catch (\Throwable $e) {
            Log::warning('SystemSetting: falha ao descriptografar', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return $default;
        }

        Cache::store()->put(self::CACHE_PREFIX . $key, $value, self::CACHE_TTL);
        return $value;
    }

    /**
     * Persiste um valor (criptografado).
     * Passar $value = null remove o registro.
     */
    public static function set(string $key, ?string $value, array $attributes = []): void
    {
        if ($value === null || $value === '') {
            self::forget($key);
            return;
        }

        $encrypted = Crypt::encryptString($value);

        static::query()->updateOrCreate(
            ['key' => $key],
            array_merge([
                'value' => $encrypted,
                'is_secret' => true,
            ], $attributes),
        );

        Cache::store()->forget(self::CACHE_PREFIX . $key);
    }

    /**
     * Remove um setting.
     */
    public static function forget(string $key): void
    {
        static::query()->where('key', $key)->delete();
        Cache::store()->forget(self::CACHE_PREFIX . $key);
    }

    /**
     * Retorna todas as chaves de um grupo em array associativo (descriptografadas).
     * Não aplica mascaramento — use apenas em contextos admin confiáveis.
     */
    public static function getGroup(string $group): array
    {
        $rows = static::query()->where('group', $group)->get();
        $result = [];
        foreach ($rows as $row) {
            if ($row->value === null || $row->value === '') {
                $result[$row->key] = null;
                continue;
            }
            try {
                $result[$row->key] = Crypt::decryptString($row->value);
            } catch (\Throwable $e) {
                $result[$row->key] = null;
            }
        }
        return $result;
    }
}
