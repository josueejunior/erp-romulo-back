<?php

namespace App\Contracts;

use App\Models\Empresa;
use App\Models\Tenant;
use App\Domain\Assinatura\Entities\Assinatura;
use Illuminate\Http\Request;

/**
 * Contrato para ApplicationContext
 * 
 * 🔥 PROTEÇÃO ARQUITETURAL: Esta interface garante que o contexto
 * seja usado corretamente e evita que alguém "burle" a arquitetura.
 * 
 * Todos os componentes devem depender desta interface, não da implementação.
 */
interface ApplicationContextContract
{
    /**
     * Bootstrap completo do contexto
     * 
     * Este método é chamado pelos middlewares e faz TUDO:
     * 1. Resolve empresa ativa
     * 2. Inicializa tenancy
     * 3. Valida assinatura (opcional)
     * 
     * 🔥 REGRA: Deve ser idempotente (pode ser chamado múltiplas vezes sem efeito)
     * 
     * @param Request $request
     * @return void
     */
    public function bootstrap(Request $request): void;
    
    /**
     * Obter empresa ativa
     * 
     * @return Empresa
     * @throws \RuntimeException Se não inicializado ou empresa não encontrada
     */
    public function empresa(): Empresa;
    
    /**
     * Obter tenant ativo
     * 
     * @return Tenant
     * @throws \RuntimeException Se não inicializado ou tenant não encontrado
     */
    public function tenant(): Tenant;
    
    /**
     * Obter assinatura ativa (se disponível)
     * 
     * @return Assinatura|null
     */
    public function assinatura(): ?Assinatura;
    
    /**
     * Verificar se há assinatura ativa
     * 
     * @return bool
     */
    public function hasAssinaturaAtiva(): bool;
    
    /**
     * Verificar se o contexto foi inicializado
     * 
     * @return bool
     */
    public function isInitialized(): bool;
    
    /**
     * Obter empresa_id
     * 
     * @return int
     * @throws \RuntimeException Se não inicializado
     */
    public function getEmpresaId(): int;
    
    /**
     * Obter tenant_id
     * 
     * @return int
     * @throws \RuntimeException Se não inicializado
     */
    public function getTenantId(): int;
    
    /**
     * Obter empresa_id ou null (sem exceção)
     * 
     * @return int|null
     */
    public function getEmpresaIdOrNull(): ?int;
    
    /**
     * Obter tenant_id ou null (sem exceção)
     * 
     * @return int|null
     */
    public function getTenantIdOrNull(): ?int;
    
    /**
     * 🔥 NOVO: Limpar cache de assinatura (útil quando assinatura é criada/atualizada)
     * 
     * Força uma nova busca da assinatura na próxima verificação
     */
    public function limparCacheAssinatura(): void;
}


