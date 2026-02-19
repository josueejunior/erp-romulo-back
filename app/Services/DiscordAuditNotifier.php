<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordAuditNotifier
{
    /**
     * Envia uma entrada de auditoria admin para o webhook do Discord.
     *
     * Chamado a partir de AdminLogger::auditAdminAction.
     */
    public static function notifyAudit(string $action, array $payload = []): void
    {
        try {
            $webhookUrl = config('services.discord_audit.webhook');
            if (!$webhookUrl) {
                return;
            }

            $adminId   = $payload['admin_id'] ?? auth()->id();
            $tenantId  = $payload['tenant_id'] ?? null;
            $empresaId = $payload['empresa_id'] ?? null;
            $resourceType = $payload['resource_type'] ?? null;
            $resourceId   = $payload['resource_id'] ?? null;

            $short = $action;
            if ($resourceType || $resourceId) {
                $short .= ' → ' . ($resourceType ?? 'resource') . ($resourceId ? " #{$resourceId}" : '');
            }

            $short = mb_substr($short, 0, 200);

            $fields = [
                [
                    'name' => 'Ação',
                    'value' => $action,
                    'inline' => false,
                ],
                [
                    'name' => 'Admin ID',
                    'value' => (string) ($adminId ?? '-'),
                    'inline' => true,
                ],
                [
                    'name' => 'Tenant',
                    'value' => (string) ($tenantId ?? '-'),
                    'inline' => true,
                ],
                [
                    'name' => 'Empresa',
                    'value' => (string) ($empresaId ?? '-'),
                    'inline' => true,
                ],
            ];

            if ($resourceType || $resourceId) {
                $fields[] = [
                    'name' => 'Recurso',
                    'value' => trim(($resourceType ?? '') . ($resourceId ? " #{$resourceId}" : '')) ?: '-',
                    'inline' => false,
                ];
            }

            if (!empty($payload['context']) && is_array($payload['context'])) {
                $contextPreview = json_encode($payload['context']);
                $contextPreview = mb_substr($contextPreview, 0, 300);
                $fields[] = [
                    'name' => 'Contexto',
                    'value' => "```json\n{$contextPreview}\n```",
                    'inline' => false,
                ];
            }

            $embed = [
                'title' => 'Auditoria Admin',
                'description' => $short,
                'color' => 3447003, // azul
                'fields' => $fields,
                'timestamp' => now()->toIso8601String(),
            ];

            Http::timeout(3)->post($webhookUrl, [
                'content' => null,
                'embeds' => [$embed],
            ]);
        } catch (\Throwable $e) {
            // Nunca deixar alerta de auditoria quebrar fluxo principal
            Log::warning('DiscordAuditNotifier::notifyAudit falhou', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

