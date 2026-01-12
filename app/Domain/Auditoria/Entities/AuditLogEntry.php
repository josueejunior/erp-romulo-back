<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Entities;

use App\Domain\Auditoria\Enums\AuditAction;
use App\Domain\Exceptions\DomainException;
use Carbon\Carbon;

/**
 * Entidade AuditLogEntry - Representa uma entrada de log de auditoria
 * 
 * Contém apenas regras de negócio, sem dependências de infraestrutura.
 * Segue princípios DDD forte.
 */
final class AuditLogEntry
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $userId,
        public readonly ?int $tenantId,
        public readonly AuditAction $action,
        public readonly string $modelType,
        public readonly ?int $modelId,
        public readonly ?array $oldValues,
        public readonly ?array $newValues,
        public readonly ?array $changes,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $description,
        public readonly Carbon $createdAt,
    ) {
        $this->validate();
        $this->calculateChanges();
    }

    /**
     * Valida regras de negócio da entidade
     */
    private function validate(): void
    {
        if (empty($this->modelType)) {
            throw new DomainException('Tipo do modelo é obrigatório para log de auditoria.');
        }

        if ($this->modelId !== null && $this->modelId <= 0) {
            throw new DomainException('ID do modelo deve ser positivo se fornecido.');
        }

        if ($this->userId !== null && $this->userId <= 0) {
            throw new DomainException('ID do usuário deve ser positivo se fornecido.');
        }

        if ($this->tenantId !== null && $this->tenantId <= 0) {
            throw new DomainException('ID do tenant deve ser positivo se fornecido.');
        }

        if (strlen($this->modelType) > 255) {
            throw new DomainException('Tipo do modelo não pode exceder 255 caracteres.');
        }

        if ($this->description !== null && strlen($this->description) > 1000) {
            throw new DomainException('Descrição não pode exceder 1000 caracteres.');
        }
    }

    /**
     * Calcula mudanças entre oldValues e newValues se não foram fornecidas
     */
    private function calculateChanges(): void
    {
        if ($this->changes !== null) {
            return; // Já foi calculado
        }

        if ($this->oldValues === null || $this->newValues === null) {
            return; // Não há o que calcular
        }

        $calculatedChanges = [];
        foreach ($this->newValues as $key => $value) {
            if (!isset($this->oldValues[$key]) || $this->oldValues[$key] !== $value) {
                $calculatedChanges[$key] = [
                    'old' => $this->oldValues[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        // Usar reflection para atualizar propriedade readonly (apenas em construção)
        // Na prática, mudanças devem ser calculadas antes de criar a entidade
    }

    /**
     * Cria uma nova entrada de auditoria
     */
    public static function criar(
        AuditAction $action,
        string $modelType,
        ?int $modelId,
        ?int $userId = null,
        ?int $tenantId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $changes = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $description = null,
    ): self {
        return new self(
            id: null,
            userId: $userId,
            tenantId: $tenantId,
            action: $action,
            modelType: $modelType,
            modelId: $modelId,
            oldValues: $oldValues,
            newValues: $newValues,
            changes: $changes ?? self::calcularMudancas($oldValues, $newValues),
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            description: $description,
            createdAt: Carbon::now(),
        );
    }

    /**
     * Calcula mudanças entre arrays
     */
    private static function calcularMudancas(?array $oldValues, ?array $newValues): ?array
    {
        if ($oldValues === null || $newValues === null) {
            return null;
        }

        $changes = [];
        foreach ($newValues as $key => $value) {
            if (!isset($oldValues[$key]) || $oldValues[$key] !== $value) {
                $changes[$key] = [
                    'old' => $oldValues[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        return empty($changes) ? null : $changes;
    }

    /**
     * Verifica se a entrada é para uma operação crítica
     */
    public function isOperacaoCritica(): bool
    {
        $criticalModelTypes = [
            'App\\Modules\\Assinatura\\Models\\Assinatura',
            'App\\Models\\PaymentLog',
            'App\\Modules\\Afiliado\\Models\\Comissao',
        ];

        return in_array($this->modelType, $criticalModelTypes) ||
               $this->action === AuditAction::STATUS_CHANGED;
    }

    /**
     * Sanitiza valores sensíveis para logs
     */
    public function sanitizeValues(): self
    {
        $sensitiveFields = ['password', 'senha', 'token', 'api_key', 'secret', 'cpf', 'cnpj'];
        
        $sanitizedOldValues = $this->sanitizeArray($this->oldValues, $sensitiveFields);
        $sanitizedNewValues = $this->sanitizeArray($this->newValues, $sensitiveFields);
        $sanitizedChanges = $this->sanitizeArray($this->changes, $sensitiveFields);

        return new self(
            id: $this->id,
            userId: $this->userId,
            tenantId: $this->tenantId,
            action: $this->action,
            modelType: $this->modelType,
            modelId: $this->modelId,
            oldValues: $sanitizedOldValues,
            newValues: $sanitizedNewValues,
            changes: $sanitizedChanges,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
            description: $this->description,
            createdAt: $this->createdAt,
        );
    }

    private function sanitizeArray(?array $data, array $sensitiveFields): ?array
    {
        if ($data === null) {
            return null;
        }

        $sanitized = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveFields) || 
                array_filter($sensitiveFields, fn($field) => str_contains($lowerKey, $field))) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value, $sensitiveFields);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Converte para array (para persistência)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'action' => $this->action->value,
            'model_type' => $this->modelType,
            'model_id' => $this->modelId,
            'old_values' => $this->oldValues,
            'new_values' => $this->newValues,
            'changes' => $this->changes,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'description' => $this->description,
            'created_at' => $this->createdAt->toDateTimeString(),
        ];
    }
}




