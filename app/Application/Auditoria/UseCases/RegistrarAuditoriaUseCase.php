<?php

declare(strict_types=1);

namespace App\Application\Auditoria\UseCases;

use App\Domain\Auditoria\Entities\AuditLogEntry;
use App\Domain\Auditoria\Enums\AuditAction;
use App\Domain\Auditoria\Repositories\AuditLogRepositoryInterface;
use App\Domain\Shared\ValueObjects\RequestContext;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Registrar Auditoria
 * 
 * Application Layer - Orquestra o registro de auditoria seguindo DDD
 */
final class RegistrarAuditoriaUseCase
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $repository,
    ) {}

    /**
     * Registra uma entrada de auditoria
     */
    public function executar(
        AuditAction $action,
        string $modelType,
        ?int $modelId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?RequestContext $context = null,
    ): AuditLogEntry {
        // Obter contexto se não fornecido
        if ($context === null) {
            $context = RequestContext::fromRequest();
        }

        // Criar entrada de auditoria
        $entry = AuditLogEntry::criar(
            action: $action,
            modelType: $modelType,
            modelId: $modelId,
            userId: $context->userId,
            tenantId: $context->tenantId,
            oldValues: $oldValues,
            newValues: $newValues,
            ipAddress: $context->ipAddress,
            userAgent: $context->userAgent,
            description: $description ?? $action->description(),
        );

        // Salvar no repositório
        $savedEntry = $this->repository->salvar($entry);

        // Log adicional para operações críticas
        if ($savedEntry->isOperacaoCritica()) {
            Log::info('Operação crítica auditada', [
                'action' => $action->value,
                'model_type' => $modelType,
                'model_id' => $modelId,
                'user_id' => $context->userId,
                'tenant_id' => $context->tenantId,
            ]);
        }

        return $savedEntry;
    }
}



