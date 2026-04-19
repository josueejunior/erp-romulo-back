<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Gerencia configurações globais do sistema via painel admin.
 *
 * Atualmente expõe apenas o grupo "mercadopago" (access_token, public_key,
 * webhook_secret, sandbox). Projetado para crescer: outros gateways e
 * integrações podem usar o mesmo padrão (grupo + chaves).
 *
 * Segurança:
 * - Valores de access_token / webhook_secret nunca são devolvidos em cleartext
 *   ao front; devolvemos apenas `has_value` + preview mascarado dos últimos 4.
 * - Só escreve quando o valor recebido é diferente de um sentinel `(mantido)`
 *   usado pelo front para preservar o valor atual.
 */
class AdminSystemSettingsController extends Controller
{
    private const MP_KEYS = [
        'mercadopago.access_token' => ['secret' => true],
        'mercadopago.public_key'   => ['secret' => false],
        'mercadopago.webhook_secret' => ['secret' => true],
        'mercadopago.sandbox'      => ['secret' => false],
    ];

    private const KEEP_SENTINEL = '(mantido)';

    /**
     * GET /admin/settings/mercadopago
     * Retorna valores mascarados + metadata.
     */
    public function showMercadoPago(): JsonResponse
    {
        $rows = [];
        foreach (self::MP_KEYS as $key => $meta) {
            $raw = SystemSetting::get($key, null);
            $envFallback = $this->envFallback($key);
            $effective = $raw ?? $envFallback;

            $isSecret = $meta['secret'];
            $rows[$this->shortKey($key)] = [
                'has_value' => !empty($effective),
                'preview'   => $isSecret ? $this->maskSecret($effective) : ($effective ?: null),
                'source'    => $raw !== null
                    ? 'database'
                    : ($envFallback !== null ? 'env' : 'empty'),
                'is_secret' => $isSecret,
            ];
        }

        return response()->json(['data' => $rows]);
    }

    /**
     * PUT /admin/settings/mercadopago
     * Atualiza uma ou mais chaves. Valor "(mantido)" preserva o atual.
     */
    public function updateMercadoPago(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'access_token'   => 'nullable|string|max:500',
            'public_key'     => 'nullable|string|max:500',
            'webhook_secret' => 'nullable|string|max:500',
            'sandbox'        => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $mapping = [
            'access_token'   => 'mercadopago.access_token',
            'public_key'     => 'mercadopago.public_key',
            'webhook_secret' => 'mercadopago.webhook_secret',
            'sandbox'        => 'mercadopago.sandbox',
        ];

        $touched = [];
        foreach ($mapping as $short => $key) {
            if (!array_key_exists($short, $data)) {
                continue;
            }
            $value = $data[$short];
            if ($value === self::KEEP_SENTINEL) {
                continue; // preservar valor atual
            }
            if ($short === 'sandbox') {
                $value = $value ? 'true' : 'false';
            }

            SystemSetting::set($key, $value === null ? null : (string) $value, [
                'group' => 'mercadopago',
                'is_secret' => self::MP_KEYS[$key]['secret'],
            ]);
            $touched[] = $short;
        }

        Log::info('MP settings atualizados via admin', [
            'admin_id' => optional($request->user())->id,
            'keys' => $touched,
        ]);

        return $this->showMercadoPago();
    }

    /**
     * POST /admin/settings/mercadopago/test
     * Testa o Access Token chamando /v1/payment_methods do Mercado Pago.
     */
    public function testMercadoPago(): JsonResponse
    {
        $token = SystemSetting::get('mercadopago.access_token', config('services.mercadopago.access_token'));
        $sandboxRaw = SystemSetting::get('mercadopago.sandbox', config('services.mercadopago.sandbox', true));
        $sandbox = is_bool($sandboxRaw) ? $sandboxRaw : filter_var($sandboxRaw, FILTER_VALIDATE_BOOLEAN);

        if (empty($token)) {
            return response()->json([
                'ok' => false,
                'message' => 'Access Token não configurado.',
            ], 400);
        }

        $expectedPrefix = $sandbox ? 'TEST-' : 'APP_USR-';
        $prefixOk = str_starts_with($token, $expectedPrefix);

        try {
            $ch = curl_init('https://api.mercadopago.com/v1/payment_methods');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                ],
            ]);
            $body = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Erro de rede: ' . $err,
                ], 502);
            }

            $decoded = json_decode((string) $body, true) ?: [];
            $ok = $status >= 200 && $status < 300 && is_array($decoded) && !empty($decoded);

            return response()->json([
                'ok' => $ok,
                'http_status' => $status,
                'sandbox' => $sandbox,
                'token_prefix_ok' => $prefixOk,
                'token_prefix' => substr($token, 0, 10) . '...',
                'payment_methods_count' => is_array($decoded) ? count($decoded) : null,
                'message' => $ok
                    ? 'Conexão com Mercado Pago OK.'
                    : ('Falha: ' . ($decoded['message'] ?? $decoded['error'] ?? 'resposta inesperada')),
            ], $ok ? 200 : 400);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Exceção ao testar: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function shortKey(string $key): string
    {
        return str_replace('mercadopago.', '', $key);
    }

    private function envFallback(string $key): ?string
    {
        return match ($key) {
            'mercadopago.access_token' => config('services.mercadopago.access_token'),
            'mercadopago.public_key' => config('services.mercadopago.public_key'),
            'mercadopago.webhook_secret' => config('services.mercadopago.webhook_secret'),
            'mercadopago.sandbox' => config('services.mercadopago.sandbox') ? 'true' : 'false',
            default => null,
        };
    }

    private function maskSecret(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }
        return substr($value, 0, 6) . str_repeat('•', max(4, $len - 10)) . substr($value, -4);
    }
}
