<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Tenant;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Serviço centralizado para gerenciar o contexto da aplicação
 * 
 * Este é o ÚNICO PONTO DE VERDADE para tenant_id e empresa_id.
 * Todos os outros componentes devem usar este serviço.
 * 
 * Padrão Singleton via Service Container do Laravel.
 */
class ApplicationContext
{
    private ?int $tenantId = null;
    private ?int $empresaId = null;
    private ?User $user = null;
    private ?Tenant $tenant = null;
    private ?Empresa $empresa = null;
    private bool $initialized = false;
    
    /**
     * Inicializar o contexto com os dados disponíveis
     * 
     * Prioridades para empresa_id:
     * 1. Header X-Empresa-ID (se usuário tem acesso)
     * 2. empresa_ativa_id do usuário
     * 3. Primeira empresa do usuário
     */
    public function initialize(?User $user = null, ?int $tenantId = null, ?int $empresaIdFromHeader = null): void
    {
        $this->user = $user;
        $this->tenantId = $tenantId;
        
        // Se já temos tenant_id, carregar o tenant
        if ($this->tenantId) {
            $this->tenant = Tenant::find($this->tenantId);
        }
        
        // Determinar empresa_id
        $this->empresaId = $this->resolveEmpresaId($empresaIdFromHeader);
        
        // Se temos empresa_id, carregar a empresa
        if ($this->empresaId) {
            $this->empresa = Empresa::find($this->empresaId);
        }
        
        $this->initialized = true;
        
        Log::debug('ApplicationContext::initialize()', [
            'tenant_id' => $this->tenantId,
            'empresa_id' => $this->empresaId,
            'user_id' => $this->user?->id,
            'initialized' => $this->initialized,
        ]);
    }
    
    /**
     * Resolver empresa_id baseado nas prioridades
     */
    private function resolveEmpresaId(?int $empresaIdFromHeader): ?int
    {
        // Prioridade 1: Header X-Empresa-ID (se usuário tem acesso)
        if ($empresaIdFromHeader && $this->user) {
            $temAcesso = $this->user->empresas()
                ->where('empresas.id', $empresaIdFromHeader)
                ->exists();
            
            if ($temAcesso) {
                Log::debug('ApplicationContext - empresaId do header', [
                    'empresa_id' => $empresaIdFromHeader
                ]);
                
                // Atualizar empresa_ativa_id do usuário se diferente
                if ($this->user->empresa_ativa_id !== $empresaIdFromHeader) {
                    $this->user->empresa_ativa_id = $empresaIdFromHeader;
                    $this->user->save();
                }
                
                return $empresaIdFromHeader;
            }
            
            Log::warning('ApplicationContext - Usuário sem acesso à empresa do header', [
                'user_id' => $this->user->id,
                'empresa_id_header' => $empresaIdFromHeader
            ]);
        }
        
        // Prioridade 2: empresa_ativa_id do usuário
        if ($this->user && $this->user->empresa_ativa_id) {
            // Verificar se a empresa existe
            $empresa = Empresa::find($this->user->empresa_ativa_id);
            if ($empresa) {
                Log::debug('ApplicationContext - empresaId do user.empresa_ativa_id', [
                    'empresa_id' => $this->user->empresa_ativa_id
                ]);
                return $this->user->empresa_ativa_id;
            }
        }
        
        // Prioridade 3: Primeira empresa do usuário
        if ($this->user) {
            $empresa = $this->user->empresas()->first();
            if ($empresa) {
                // Atualizar empresa_ativa_id do usuário
                $this->user->empresa_ativa_id = $empresa->id;
                $this->user->save();
                
                Log::debug('ApplicationContext - empresaId da primeira empresa do usuário', [
                    'empresa_id' => $empresa->id
                ]);
                return $empresa->id;
            }
        }
        
        Log::warning('ApplicationContext - Nenhum empresaId encontrado', [
            'user_id' => $this->user?->id,
            'tenant_id' => $this->tenantId
        ]);
        
        return null;
    }
    
    /**
     * Verificar se o contexto foi inicializado
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }
    
    /**
     * Obter tenant_id (lança exceção se não inicializado)
     */
    public function getTenantId(): int
    {
        if (!$this->initialized) {
            throw new \RuntimeException('ApplicationContext não foi inicializado. Verifique se o middleware está configurado.');
        }
        
        if (!$this->tenantId) {
            throw new \RuntimeException('Tenant não identificado no contexto.');
        }
        
        return $this->tenantId;
    }
    
    /**
     * Obter tenant_id ou null (sem exceção)
     */
    public function getTenantIdOrNull(): ?int
    {
        return $this->tenantId;
    }
    
    /**
     * Obter empresa_id (lança exceção se não inicializado)
     */
    public function getEmpresaId(): int
    {
        if (!$this->initialized) {
            throw new \RuntimeException('ApplicationContext não foi inicializado. Verifique se o middleware está configurado.');
        }
        
        if (!$this->empresaId) {
            throw new \RuntimeException('Empresa não identificada no contexto.');
        }
        
        return $this->empresaId;
    }
    
    /**
     * Obter empresa_id ou null (sem exceção)
     */
    public function getEmpresaIdOrNull(): ?int
    {
        return $this->empresaId;
    }
    
    /**
     * Obter usuário autenticado
     */
    public function getUser(): ?User
    {
        return $this->user;
    }
    
    /**
     * Obter tenant carregado
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }
    
    /**
     * Obter empresa carregada
     */
    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }
    
    /**
     * Atualizar empresa_id (quando usuário troca de empresa)
     */
    public function setEmpresaId(int $empresaId): void
    {
        // Verificar se usuário tem acesso
        if ($this->user) {
            $temAcesso = $this->user->empresas()
                ->where('empresas.id', $empresaId)
                ->exists();
            
            if (!$temAcesso) {
                throw new \RuntimeException('Usuário não tem acesso a esta empresa.');
            }
            
            // Atualizar no banco
            $this->user->empresa_ativa_id = $empresaId;
            $this->user->save();
        }
        
        $this->empresaId = $empresaId;
        $this->empresa = Empresa::find($empresaId);
        
        Log::debug('ApplicationContext::setEmpresaId()', [
            'empresa_id' => $empresaId
        ]);
    }
    
    /**
     * Limpar contexto (útil para testes e jobs)
     */
    public function clear(): void
    {
        $this->tenantId = null;
        $this->empresaId = null;
        $this->user = null;
        $this->tenant = null;
        $this->empresa = null;
        $this->initialized = false;
    }
    
    /**
     * Helper estático para acesso rápido ao serviço
     */
    public static function current(): self
    {
        return app(self::class);
    }
}
