<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Auditoria\Entities\AuditLogEntry;
use App\Domain\Auditoria\Enums\AuditAction;
use App\Domain\Auditoria\Repositories\AuditLogRepositoryInterface;
use App\Models\AuditLog as AuditLogModel;
use Carbon\Carbon;

/**
 * Implementação do Repository de AuditLog usando Eloquent
 * 
 * Infrastructure Layer - Conhece detalhes de persistência (Eloquent)
 */
final class AuditLogRepository implements AuditLogRepositoryInterface
{
    /**
     * Converte modelo Eloquent para entidade de domínio
     */
    private function toDomain(AuditLogModel $model): AuditLogEntry
    {
        return new AuditLogEntry(
            id: $model->id,
            userId: $model->usuario_id,
            tenantId: $model->tenant_id ?? null,
            action: AuditAction::from($model->action),
            modelType: $model->model_type,
            modelId: $model->model_id,
            oldValues: $model->old_values,
            newValues: $model->new_values,
            changes: $model->changes,
            ipAddress: $model->ip_address,
            userAgent: $model->user_agent,
            description: $model->description,
            createdAt: Carbon::parse($model->created_at),
        );
    }

    /**
     * Converte entidade de domínio para array para persistência
     */
    private function toArray(AuditLogEntry $entry): array
    {
        return [
            'usuario_id' => $entry->userId,
            'tenant_id' => $entry->tenantId,
            'action' => $entry->action->value,
            'model_type' => $entry->modelType,
            'model_id' => $entry->modelId,
            'old_values' => $entry->oldValues,
            'new_values' => $entry->newValues,
            'changes' => $entry->changes,
            'ip_address' => $entry->ipAddress,
            'user_agent' => $entry->userAgent,
            'description' => $entry->description,
            'created_at' => $entry->createdAt->toDateTimeString(),
        ];
    }

    public function salvar(AuditLogEntry $entry): AuditLogEntry
    {
        // Sanitizar valores sensíveis antes de salvar
        $sanitizedEntry = $entry->sanitizeValues();
        $data = $this->toArray($sanitizedEntry);

        if ($entry->id !== null) {
            $model = AuditLogModel::findOrFail($entry->id);
            $model->update($data);
            return $this->toDomain($model->fresh());
        }

        $model = AuditLogModel::create($data);
        return $this->toDomain($model);
    }

    public function buscarPorId(int $id): ?AuditLogEntry
    {
        $model = AuditLogModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorModelo(string $modelType, ?int $modelId = null, ?int $limit = null): array
    {
        $query = AuditLogModel::where('model_type', $modelType);
        
        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->orderByDesc('created_at')
            ->get()
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }

    public function buscarPorAcao(AuditAction $action, ?int $limit = null): array
    {
        $query = AuditLogModel::where('action', $action->value);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->orderByDesc('created_at')
            ->get()
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }

    public function buscarPorUsuario(int $userId, ?int $limit = null): array
    {
        $query = AuditLogModel::where('usuario_id', $userId);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->orderByDesc('created_at')
            ->get()
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }

    public function buscarPorTenant(int $tenantId, ?int $limit = null): array
    {
        $query = AuditLogModel::where('tenant_id', $tenantId);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->orderByDesc('created_at')
            ->get()
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }

    public function buscarOperacoesCriticas(?int $limit = null): array
    {
        $criticalActions = array_map(
            fn(AuditAction $action) => $action->value,
            array_filter(AuditAction::cases(), fn($action) => $action->isCritical())
        );

        $query = AuditLogModel::whereIn('action', $criticalActions);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->orderByDesc('created_at')
            ->get()
            ->map(fn($model) => $this->toDomain($model))
            ->toArray();
    }
}





