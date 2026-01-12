<?php

declare(strict_types=1);

namespace App\Domain\Auditoria\Repositories;

use App\Domain\Auditoria\Entities\AuditLogEntry;
use App\Domain\Auditoria\Enums\AuditAction;

/**
 * Interface do Repository de AuditLog
 * 
 * O domínio não sabe se é MySQL, MongoDB, API, etc.
 * Segue DDD forte: Interface no domínio, implementação na infraestrutura
 */
interface AuditLogRepositoryInterface
{
    /**
     * Salva uma entrada de auditoria
     */
    public function salvar(AuditLogEntry $entry): AuditLogEntry;

    /**
     * Busca uma entrada por ID
     */
    public function buscarPorId(int $id): ?AuditLogEntry;

    /**
     * Busca entradas por tipo de modelo
     */
    public function buscarPorModelo(string $modelType, ?int $modelId = null, ?int $limit = null): array;

    /**
     * Busca entradas por ação
     */
    public function buscarPorAcao(AuditAction $action, ?int $limit = null): array;

    /**
     * Busca entradas por usuário
     */
    public function buscarPorUsuario(int $userId, ?int $limit = null): array;

    /**
     * Busca entradas por tenant
     */
    public function buscarPorTenant(int $tenantId, ?int $limit = null): array;

    /**
     * Busca entradas de operações críticas
     */
    public function buscarOperacoesCriticas(?int $limit = null): array;
}




