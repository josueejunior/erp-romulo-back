<?php

namespace App\Support\Logging;

use Illuminate\Support\Facades\Log;
use App\Models\AdminAuditLog;
use App\Services\DiscordAuditNotifier;

/**
 * Trait de logging estruturado para controllers/admin.
 *
 * Objetivo:
 * - Centralizar contexto padrão (rota, método, admin, tenant, request_id)
 * - Reduzir logs duplicados/verbosos espalhados pelo código
 * - Padronizar tratamento de exceções em endpoints admin
 */
trait AdminLogger
{
    /**
     * Monta o contexto base (sempre presente) para logs admin.
     */
    protected function adminBaseContext(): array
    {
        $request = request();

        return [
            'path'       => $request?->path(),
            'method'     => $request?->method(),
            'admin_id'   => auth()->id(),
            'tenant_id'  => function_exists('tenancy') && tenancy()->initialized ? (tenancy()->tenant?->id ?? null) : null,
            'request_id' => $request?->header('X-Request-Id'),
        ];
    }

    /**
     * Log genérico com contexto padronizado.
     */
    protected function logAdmin(string $level, string $message, array $context = []): void
    {
        $base = $this->adminBaseContext();

        Log::log($level, $message, array_merge($base, $context));
    }

    protected function logAdminInfo(string $message, array $context = []): void
    {
        $this->logAdmin('info', $message, $context);
    }

    protected function logAdminDebug(string $message, array $context = []): void
    {
        $this->logAdmin('debug', $message, $context);
    }

    protected function logAdminWarning(string $message, array $context = []): void
    {
        $this->logAdmin('warning', $message, $context);
    }

    protected function logAdminError(\Throwable $e, string $message, array $context = []): void
    {
        $extra = [
            'error'     => $e->getMessage(),
            'exception' => get_class($e),
        ];

        // Em modo debug, anexar stack trace também
        if (config('app.debug')) {
            $extra['trace'] = $e->getTraceAsString();
        }

        $this->logAdmin('error', $message, array_merge($context, $extra));
    }

    /**
     * Registra uma ação administrativa em tabela de auditoria.
     *
     * @param string $action        Ex: user.created, backup.created, tenant.schema_repair
     * @param array  $data          Pode conter: resource_type, resource_id, tenant_id, empresa_id, qualquer outro contexto
     */
    protected function auditAdminAction(string $action, array $data = []): void
    {
        try {
            $request = request();

            $resourceType = $data['resource_type'] ?? null;
            $resourceId   = $data['resource_id'] ?? null;
            $tenantId     = $data['tenant_id'] ?? (function_exists('tenancy') && tenancy()->initialized ? (tenancy()->tenant?->id ?? null) : null);
            $empresaId    = $data['empresa_id'] ?? null;

            // Remover chaves reservadas do contexto
            $context = $data;
            unset($context['resource_type'], $context['resource_id'], $context['tenant_id'], $context['empresa_id']);

            $log = AdminAuditLog::create([
                'admin_id'      => auth()->id(),
                'action'        => $action,
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
                'tenant_id'     => $tenantId,
                'empresa_id'    => $empresaId,
                'ip_address'    => $request?->ip(),
                'user_agent'    => $request?->userAgent(),
                'context'       => $context ?: null,
            ]);

            // Enviar também para o Discord (canal de auditoria)
            DiscordAuditNotifier::notifyAudit($action, [
                'admin_id'      => $log->admin_id,
                'tenant_id'     => $log->tenant_id,
                'empresa_id'    => $log->empresa_id,
                'resource_type' => $log->resource_type,
                'resource_id'   => $log->resource_id,
                'context'       => $log->context,
            ]);
        } catch (\Throwable $e) {
            // Nunca deixar audit falhar a request principal
            Log::warning('AdminLogger::auditAdminAction - Falha ao registrar log de auditoria', [
                'action'  => $action,
                'data'    => $data,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Tratamento padrão de exceções em endpoints Admin.
     *
     * - Loga erro com contexto enriquecido
     * - Retorna ApiResponse de erro padronizada
     */
    protected function handleAdminException(\Throwable $e, string $publicMessage = 'Erro ao processar requisição.', int $status = 500)
    {
        $this->logAdminError($e, $publicMessage);

        return \App\Http\Responses\ApiResponse::error($publicMessage, $status);
    }
}

