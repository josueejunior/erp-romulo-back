<?php

namespace App\Application\Shared\Traits;

use App\Services\ApplicationContext;
use App\Domain\Shared\ValueObjects\TenantContext;

/**
 * Trait para UseCases que precisam do contexto da aplicação
 * 
 * Fornece acesso padronizado a tenant_id e empresa_id,
 * com fallbacks robustos para diferentes cenários.
 */
trait HasApplicationContext
{
    /**
     * Obter empresa_id do contexto (com fallbacks)
     * 
     * Prioridade:
     * 1. Valor passado como parâmetro (DTO)
     * 2. ApplicationContext (novo serviço centralizado)
     * 3. TenantContext (compatibilidade DDD)
     * 4. Container 'current_empresa_id' (compatibilidade legado)
     */
    protected function resolveEmpresaId(?int $empresaIdFromDto = null): int
    {
        // Prioridade 1: Valor do DTO
        if ($empresaIdFromDto && $empresaIdFromDto > 0) {
            return $empresaIdFromDto;
        }
        
        // Prioridade 2: ApplicationContext (novo)
        if (app()->bound(ApplicationContext::class)) {
            $context = app(ApplicationContext::class);
            if ($context->isInitialized() && $context->getEmpresaIdOrNull()) {
                return $context->getEmpresaId();
            }
        }
        
        // Prioridade 3: TenantContext (DDD)
        if (TenantContext::has()) {
            $tenantContext = TenantContext::get();
            if ($tenantContext->empresaId) {
                return $tenantContext->empresaId;
            }
        }
        
        // Prioridade 4: Container legado
        if (app()->bound('current_empresa_id')) {
            $empresaId = app('current_empresa_id');
            if ($empresaId && $empresaId > 0) {
                return $empresaId;
            }
        }
        
        throw new \DomainException('Empresa não identificada no contexto. Verifique se o middleware está configurado.');
    }
    
    /**
     * Obter empresa_id ou null (sem exceção)
     */
    protected function resolveEmpresaIdOrNull(?int $empresaIdFromDto = null): ?int
    {
        try {
            return $this->resolveEmpresaId($empresaIdFromDto);
        } catch (\DomainException $e) {
            return null;
        }
    }
    
    /**
     * Obter tenant_id do contexto
     */
    protected function resolveTenantId(): int
    {
        // Prioridade 1: ApplicationContext
        if (app()->bound(ApplicationContext::class)) {
            $context = app(ApplicationContext::class);
            if ($context->isInitialized()) {
                return $context->getTenantId();
            }
        }
        
        // Prioridade 2: TenantContext (DDD)
        if (TenantContext::has()) {
            return TenantContext::get()->tenantId;
        }
        
        // Prioridade 3: tenancy()
        if (tenancy()->tenant) {
            return tenancy()->tenant->id;
        }
        
        throw new \DomainException('Tenant não identificado no contexto. Verifique se o middleware está configurado.');
    }
    
    /**
     * Obter ApplicationContext completo
     */
    protected function getApplicationContext(): ApplicationContext
    {
        return app(ApplicationContext::class);
    }
}
