<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordErrorNotifier
{
    /**
     * Envia notificação de erro 5xx para o webhook do Discord.
     *
     * Pensado para ser chamado a partir do middleware HandleApiErrors.
     */
    public static function notifyHttpError(Request $request, int $status, ?\Throwable $e = null): void
    {
        try {
            $webhookUrl = config('services.discord_errors.webhook');
            if (!$webhookUrl) {
                // Nada configurado, não faz nada
                return;
            }

            $shortMessage = $e?->getMessage() ?: 'Erro HTTP ' . $status;
            $shortMessage = mb_substr($shortMessage, 0, 150);

            $content = sprintf(
                '🚨 Erro %d na API: `%s` %s',
                $status,
                $request->method() . ' ' . $request->path(),
                $request->user()?->email ? '(user: ' . $request->user()->email . ')' : ''
            );

            $embed = [
                'title' => 'Erro na API (' . $status . ')',
                'description' => $shortMessage,
                'color' => 15158332, // vermelho
                'fields' => [
                    [
                        'name' => 'URL',
                        'value' => $request->fullUrl(),
                        'inline' => false,
                    ],
                    [
                        'name' => 'Método',
                        'value' => $request->method(),
                        'inline' => true,
                    ],
                    [
                        'name' => 'Status',
                        'value' => (string) $status,
                        'inline' => true,
                    ],
                    [
                        'name' => 'IP',
                        'value' => $request->ip(),
                        'inline' => true,
                    ],
                ],
                'timestamp' => now()->toIso8601String(),
            ];

            if ($e) {
                $embed['fields'][] = [
                    'name' => 'Exception',
                    'value' => get_class($e),
                    'inline' => false,
                ];
            }

            Http::timeout(3)->post($webhookUrl, [
                'content' => $content,
                'embeds' => [$embed],
            ]);
        } catch (\Throwable $notifyException) {
            // Nunca deixar o alerta quebrar a request principal
            Log::warning('DiscordErrorNotifier::notifyHttpError falhou', [
                'error' => $notifyException->getMessage(),
            ]);
        }
    }
}

