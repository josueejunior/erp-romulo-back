<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Tenant;
use App\Modules\Auth\Models\User;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Assinatura\Entities\Assinatura;
use App\Contracts\ApplicationContextContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/**
 * Serviço centralizado para gerenciar o contexto da aplicação
 * 
 * Este é o ÚNICO PONTO DE VERDADE para:
 * - Resolução de empresa ativa
 * - Inicialização de tenancy
 * - Validação/busca de assinatura
 * 
 * Todos os outros componentes devem usar este serviço.
 * 
 * Padrão Singleton via Service Container do Laravel.
 */
class ApplicationContext implements ApplicationContextContract
{
    private ?int $tenantId = null;
    private ?int $empresaId = null;
    private ?User $user = null;
    private ?Tenant $tenant = null;
    private ?Empresa $empresa = null;
    private ?Assinatura $assinatura = null;
    private bool $initialized = false;
    private bool $tenancyInitialized = false;
    private ?array $assinaturaCache = null;
    private int $bootstrapCallCount = 0;
    
    public function __construct(
        private ?AssinaturaRepositoryInterface $assinaturaRepository = null
    ) {
        // Injetar via container se não fornecido
        if (!$this->assinaturaRepository && app()->bound(AssinaturaRepositoryInterface::class)) {
            $this->assinaturaRepository = app(AssinaturaRepositoryInterface::class);
        }
    }
    
    /**
     * 🧠 MÉTODO PRINCIPAL: Bootstrap completo do contexto
     * 
     * Este método é chamado pelos middlewares e faz TUDO:
     * 1. Resolve empresa ativa
     * 2. Inicializa tenancy
     * 3. Valida assinatura (opcional)
     * 
     * 🔥 REGRA: Deve ser idempotente (pode ser chamado múltiplas vezes sem efeito)
     * Se for chamado mais de 1 vez por request → log de warning (bug arquitetural)
     * 
     * @param Request $request
     * @return void
     */
    public function bootstrap(Request $request): void
    {
        $this->bootstrapCallCount++;
        
        // Se já inicializado, não fazer nada (idempotente)
        if ($this->initialized && $this->tenancyInitialized) {
            if ($this->bootstrapCallCount > 1) {
                Log::warning('ApplicationContext::bootstrap() - Chamado múltiplas vezes no mesmo request', [
                    'call_count' => $this->bootstrapCallCount,
                    'url' => $request->url(),
                    'method' => $request->method(),
                    'stack_trace' => array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10), 0, 5),
                ]);
            } else {
                Log::debug('ApplicationContext::bootstrap() - Já inicializado, pulando');
            }
            return;
        }
        
        Log::info('ApplicationContext::bootstrap() - Iniciando bootstrap', [
            'call_count' => $this->bootstrapCallCount,
            'url' => $request->url(),
            'method' => $request->method(),
        ]);
        
        // 1. Obter usuário autenticado
        if (config('app.debug')) {
            Log::debug('ApplicationContext::bootstrap() - Buscando usuário autenticado');
        }
        $startTime = microtime(true);
        $this->user = auth('sanctum')->user();
        $elapsedTime = microtime(true) - $startTime;
        if (config('app.debug')) {
            Log::debug('ApplicationContext::bootstrap() - auth("sanctum")->user() concluído', [
                'elapsed_time' => round($elapsedTime, 3) . 's',
                'user_id' => $this->user?->id,
            ]);
        }
        
        if (!$this->user) {
            Log::debug('ApplicationContext::bootstrap() - Sem usuário autenticado');
            return;
        }
        
        // Se for admin, não precisa de empresa/tenant
        if (method_exists($this->user, 'isAdmin') && $this->user->isAdmin()) {
            Log::debug('ApplicationContext::bootstrap() - Usuário é admin, pulando');
            $this->initialized = true;
            return;
        }
        
        // 2. Resolver empresa ativa
        Log::debug('ApplicationContext::bootstrap() - Resolvendo empresa ativa');
        $empresaIdFromHeader = $request->header('X-Empresa-ID') 
            ? (int) $request->header('X-Empresa-ID') 
            : null;
        
        $startTime = microtime(true);
        $this->empresaId = $this->resolveEmpresaId($empresaIdFromHeader);
        $elapsedTime = microtime(true) - $startTime;
        Log::debug('ApplicationContext::bootstrap() - resolveEmpresaId() concluído', [
            'elapsed_time' => round($elapsedTime, 3) . 's',
            'empresa_id' => $this->empresaId,
        ]);
        
        if (!$this->empresaId) {
            Log::warning('ApplicationContext::bootstrap() - Nenhum empresaId encontrado', [
                'user_id' => $this->user->id,
            ]);
            $this->initialized = true;
            return;
        }
        
        // 3. Resolver tenant_id através da empresa
        if (config('app.debug')) {
            Log::debug('ApplicationContext::bootstrap() - Resolvendo tenant_id');
        }
        $startTime = microtime(true);
        $this->tenantId = $this->resolveTenantId();
        $elapsedTime = microtime(true) - $startTime;
        if (config('app.debug')) {
            Log::debug('ApplicationContext::bootstrap() - resolveTenantId() concluído', [
                'elapsed_time' => round($elapsedTime, 3) . 's',
                'tenant_id' => $this->tenantId,
            ]);
        }
        
        if (!$this->tenantId) {
            Log::warning('ApplicationContext::bootstrap() - Nenhum tenantId encontrado', [
                'user_id' => $this->user->id,
                'empresa_id' => $this->empresaId,
            ]);
            $this->initialized = true;
            return;
        }
        
        // 4. Inicializar tenancy (com proteção)
        if (config('app.debug')) {
            Log::debug('ApplicationContext::bootstrap() - Inicializando tenancy');
        }
        $startTime = microtime(true);
        $this->initializeTenancy();
        $elapsedTime = microtime(true) - $startTime;
        if (config('app.debug')) {
            Log::debug('ApplicationContext::bootstrap() - initializeTenancy() concluído', [
                'elapsed_time' => round($elapsedTime, 3) . 's',
            ]);
        }
        
        // 5. Carregar modelos
        $this->tenant = Tenant::find($this->tenantId);
        $this->empresa = Empresa::find($this->empresaId);
        
        // 6. Sincronizar com TenantContext (compatibilidade DDD)
        if (class_exists(\App\Domain\Shared\ValueObjects\TenantContext::class)) {
            \App\Domain\Shared\ValueObjects\TenantContext::set($this->tenantId, $this->empresaId);
        }
        
        // 7. Disponibilizar no container (compatibilidade com código legado)
        app()->instance('current_empresa_id', $this->empresaId);
        $request->attributes->set('empresa_id', $this->empresaId);
        
        // 8. Compartilhar contexto de logs
        Log::shareContext([
            'tenant_id' => $this->tenantId,
            'empresa_id' => $this->empresaId,
            'user_id' => $this->user->id,
        ]);
        
        $this->initialized = true;
        
        Log::debug('ApplicationContext::bootstrap() - Concluído', [
            'tenant_id' => $this->tenantId,
            'empresa_id' => $this->empresaId,
            'user_id' => $this->user->id,
        ]);
    }
    
    /**
     * Inicializar o contexto com os dados disponíveis (método legado)
     * 
     * @deprecated Use bootstrap() ao invés deste método
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
     * Obter empresa ativa (método da interface)
     * 
     * @return Empresa
     * @throws \RuntimeException Se não inicializado ou empresa não encontrada
     */
    public function empresa(): Empresa
    {
        if (!$this->initialized) {
            throw new \RuntimeException('ApplicationContext não foi inicializado. Verifique se o middleware está configurado.');
        }
        
        if (!$this->empresa) {
            throw new \RuntimeException('Empresa não encontrada no contexto.');
        }
        
        return $this->empresa;
    }
    
    /**
     * Obter tenant ativo (método da interface)
     * 
     * @return Tenant
     * @throws \RuntimeException Se não inicializado ou tenant não encontrado
     */
    public function tenant(): Tenant
    {
        if (!$this->initialized) {
            throw new \RuntimeException('ApplicationContext não foi inicializado. Verifique se o middleware está configurado.');
        }
        
        if (!$this->tenant) {
            throw new \RuntimeException('Tenant não encontrado no contexto.');
        }
        
        return $this->tenant;
    }
    
    /**
     * Obter assinatura ativa (método da interface)
     * 
     * @return Assinatura|null
     */
    public function assinatura(): ?Assinatura
    {
        if (!$this->initialized || !$this->user) {
            return null;
        }
        
        // Se já temos a assinatura carregada, retornar
        if ($this->assinatura) {
            return $this->assinatura;
        }
        
        // Buscar assinatura se não temos ainda
        // 🔥 NOVO: Buscar assinatura por empresa, não por usuário
        if ($this->assinaturaRepository && $this->empresaId) {
            try {
                $this->assinatura = $this->assinaturaRepository->buscarAssinaturaAtualPorEmpresa($this->empresaId);
            } catch (\Exception $e) {
                Log::warning('ApplicationContext::assinatura() - Erro ao buscar assinatura', [
                    'empresa_id' => $this->empresaId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return $this->assinatura;
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
     * Inicializar tenancy (com proteção para não inicializar 2x)
     * 
     * Regra: tenancy só pode ser iniciado 1 vez por request
     * Se tentar de novo → ignora ou lança exceção
     */
    private function initializeTenancy(): void
    {
        if ($this->tenancyInitialized) {
            Log::debug('ApplicationContext::initializeTenancy() - Já inicializado, pulando');
            return;
        }
        
        if (!$this->tenantId) {
            throw new \RuntimeException('Não é possível inicializar tenancy sem tenant_id');
        }
        
        // Verificar se já está inicializado com outro tenant
        if (tenancy()->initialized) {
            $currentTenantId = tenancy()->tenant?->id;
            
            if ($currentTenantId === $this->tenantId) {
                Log::debug('ApplicationContext::initializeTenancy() - Já inicializado com tenant correto', [
                    'tenant_id' => $this->tenantId,
                ]);
                $this->tenancyInitialized = true;
                return;
            }
            
            // Tenant diferente, reinicializar
            Log::info('ApplicationContext::initializeTenancy() - Reinicializando tenancy', [
                'tenant_id_atual' => $currentTenantId,
                'tenant_id_correto' => $this->tenantId,
            ]);
            
            tenancy()->end();
        }
        
        // Buscar e inicializar tenant
        $tenant = Tenant::find($this->tenantId);
        
        if (!$tenant) {
            throw new \RuntimeException("Tenant não encontrado: {$this->tenantId}");
        }
        
        tenancy()->initialize($tenant);
        $this->tenancyInitialized = true;
        
        Log::debug('ApplicationContext::initializeTenancy() - Tenancy inicializado', [
            'tenant_id' => $this->tenantId,
        ]);
    }
    
    /**
     * Resolver tenant_id através da empresa ativa
     * 
     * 🔥 PERFORMANCE: Usa mapeamento direto tenant_empresas.
     * Elimina loops de tenants e inicializações desnecessárias.
     */
    private function resolveTenantId(): ?int
    {
        if (!$this->empresaId) {
            return null;
        }
        
        // Prioridade 1: Mapeamento direto (mais rápido - busca única no banco central)
        try {
            $tenantId = \App\Models\TenantEmpresa::findTenantIdByEmpresaId($this->empresaId);
            
            if ($tenantId) {
                Log::debug('ApplicationContext::resolveTenantId() - Tenant encontrado via mapeamento direto', [
                    'empresa_id' => $this->empresaId,
                    'tenant_id' => $tenantId,
                ]);
                return $tenantId;
            }
        } catch (\Exception $e) {
            Log::warning('ApplicationContext::resolveTenantId() - Erro ao buscar mapeamento direto', [
                'empresa_id' => $this->empresaId,
                'error' => $e->getMessage(),
            ]);
            // Continuar para fallback
        }
        
        // Prioridade 2: Se já temos tenancy inicializado, verificar se a empresa está nele
        if (tenancy()->initialized) {
            $currentTenant = tenancy()->tenant;
            try {
                $empresa = Empresa::find($this->empresaId);
                if ($empresa) {
                    Log::debug('ApplicationContext::resolveTenantId() - Empresa encontrada no tenant atual', [
                        'empresa_id' => $this->empresaId,
                        'tenant_id' => $currentTenant->id,
                    ]);
                    
                    // Criar mapeamento para próxima vez (cache)
                    try {
                        \App\Models\TenantEmpresa::createOrUpdateMapping($currentTenant->id, $this->empresaId);
                    } catch (\Exception $e) {
                        Log::warning('ApplicationContext::resolveTenantId() - Erro ao criar mapeamento', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                    
                    return $currentTenant->id;
                }
            } catch (\Exception $e) {
                Log::debug('ApplicationContext::resolveTenantId() - Erro ao verificar tenant atual', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Prioridade 3: Fallback - Buscar tenant através da empresa (loop - apenas se mapeamento não existir)
        // ⚠️ Este é o método antigo, lento. Deve ser usado apenas se mapeamento não existir.
        Log::warning('ApplicationContext::resolveTenantId() - Mapeamento não encontrado, usando fallback (loop)', [
            'empresa_id' => $this->empresaId,
            'message' => 'Considere executar o comando para popular o mapeamento: php artisan tenant-empresas:popular',
        ]);
        
        $allTenants = Tenant::all();
        foreach ($allTenants as $tenant) {
            // Pular o tenant atual se já verificamos
            if (tenancy()->initialized && tenancy()->tenant && tenancy()->tenant->id === $tenant->id) {
                continue;
            }
            
            try {
                // Inicializar contexto do tenant
                tenancy()->initialize($tenant);
                
                try {
                    // Tentar buscar empresa neste tenant
                    $empresa = Empresa::find($this->empresaId);
                    if ($empresa) {
                        // Empresa encontrada - criar mapeamento para próxima vez
                        try {
                            \App\Models\TenantEmpresa::createOrUpdateMapping($tenant->id, $this->empresaId);
                            Log::info('ApplicationContext::resolveTenantId() - Mapeamento criado automaticamente', [
                                'tenant_id' => $tenant->id,
                                'empresa_id' => $this->empresaId,
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('ApplicationContext::resolveTenantId() - Erro ao criar mapeamento', [
                                'error' => $e->getMessage(),
                            ]);
                        }
                        
                        Log::debug('ApplicationContext::resolveTenantId() - Tenant encontrado via fallback', [
                            'empresa_id' => $this->empresaId,
                            'tenant_id' => $tenant->id,
                        ]);
                        return $tenant->id;
                    }
                } finally {
                    // Sempre finalizar contexto se não for o tenant correto
                    if (tenancy()->initialized && tenancy()->tenant && tenancy()->tenant->id !== $tenant->id) {
                        tenancy()->end();
                    }
                }
            } catch (\Exception $e) {
                // Se houver erro ao acessar o tenant, continuar para o próximo
                Log::debug('ApplicationContext::resolveTenantId() - Erro ao buscar empresa no tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
                continue;
            }
        }
        
        Log::warning('ApplicationContext::resolveTenantId() - Empresa não encontrada em nenhum tenant', [
            'empresa_id' => $this->empresaId,
        ]);
        
        return null;
    }
    
    /**
     * Verificar se há assinatura ativa (com cache)
     * 
     * @return bool
     */
    public function hasAssinaturaAtiva(): bool
    {
        if ($this->assinaturaCache !== null) {
            return $this->assinaturaCache['pode_acessar'] ?? false;
        }
        
        $this->validateAssinatura();
        
        return $this->assinaturaCache['pode_acessar'] ?? false;
    }
    
    /**
     * Validar assinatura e cachear resultado
     * 
     * @return array
     */
    public function validateAssinatura(): array
    {
        if ($this->assinaturaCache !== null) {
            return $this->assinaturaCache;
        }
        
        if (!$this->user) {
            $this->assinaturaCache = [
                'pode_acessar' => false,
                'code' => 'UNAUTHENTICATED',
                'message' => 'Usuário não autenticado',
            ];
            return $this->assinaturaCache;
        }
        
        if (!$this->assinaturaRepository) {
            Log::warning('ApplicationContext::validateAssinatura() - Repository não disponível');
            $this->assinaturaCache = [
                'pode_acessar' => true, // Permitir acesso se não conseguir validar
                'code' => 'SUBSCRIPTION_CHECK_SKIPPED',
            ];
            return $this->assinaturaCache;
        }
        
        // 🔥 NOVO: Verificar se temos empresa_id (obrigatório para buscar assinatura)
        if (!$this->empresaId) {
            $this->assinaturaCache = [
                'pode_acessar' => false,
                'code' => 'NO_EMPRESA',
                'message' => 'Empresa não definida no contexto.',
            ];
            return $this->assinaturaCache;
        }

        try {
            // 🔥 NOVO: Buscar assinatura da empresa, não do usuário
            $assinatura = $this->assinaturaRepository->buscarAssinaturaAtualPorEmpresa($this->empresaId);
            
            
            if (!$assinatura) {
                Log::warning('ApplicationContext::validateAssinatura - Assinatura não encontrada', [
                    'empresa_id' => $this->empresaId,
                    'user_id' => $this->user?->id,
                ]);
                
                $this->assinaturaCache = [
                    'pode_acessar' => false,
                    'code' => 'NO_SUBSCRIPTION',
                    'message' => 'Esta empresa não possui uma assinatura ativa. Contrate um plano para continuar usando o sistema.',
                    'action' => 'subscribe',
                ];
                return $this->assinaturaCache;
            }
            
            // Validar status
            $hoje = \Carbon\Carbon::now()->startOfDay();
            $dataFim = $assinatura->dataFim?->startOfDay();
            
            if (!$dataFim) {
                $this->assinaturaCache = [
                    'pode_acessar' => false,
                    'code' => 'INVALID_SUBSCRIPTION',
                    'message' => 'Assinatura com data de término inválida.',
                ];
                return $this->assinaturaCache;
            }
            
            $diasRestantes = (int) $hoje->diffInDays($dataFim, false);
            $diasExpirado = $diasRestantes < 0 ? abs($diasRestantes) : 0;
            $diasGracePeriod = $assinatura->diasGracePeriod ?? 7;
            $estaNoGracePeriod = $diasRestantes < 0 && abs($diasRestantes) <= $diasGracePeriod;
            $estaAtiva = $diasRestantes >= 0 || $estaNoGracePeriod;
            
            if ($assinatura->status === 'suspensa') {
                $this->assinaturaCache = [
                    'pode_acessar' => false,
                    'code' => 'SUBSCRIPTION_SUSPENDED',
                    'message' => 'A assinatura desta empresa está suspensa. Entre em contato com o suporte.',
                ];
                return $this->assinaturaCache;
            }
            
            if ($estaAtiva) {
                $warning = $estaNoGracePeriod ? [
                    'warning' => true,
                    'dias_expirado' => $diasExpirado,
                ] : null;
                
                $this->assinaturaCache = [
                    'pode_acessar' => true,
                    'code' => 'SUBSCRIPTION_ACTIVE',
                    'warning' => $warning,
                ];
                return $this->assinaturaCache;
            }
            
            // Expirada
            $this->assinaturaCache = [
                'pode_acessar' => false,
                'code' => 'SUBSCRIPTION_EXPIRED',
                'message' => 'A assinatura desta empresa expirou em ' . $dataFim->format('d/m/Y') . '. Renove sua assinatura para continuar usando o sistema.',
                'data_vencimento' => $dataFim->format('Y-m-d'),
                'dias_expirado' => $diasExpirado,
                'action' => 'renew',
            ];
            return $this->assinaturaCache;
            
        } catch (\Exception $e) {
            Log::error('ApplicationContext::validateAssinatura() - Erro', [
                'empresa_id' => $this->empresaId,
                'user_id' => $this->user?->id,
                'error' => $e->getMessage(),
            ]);
            
            // Em caso de erro, bloquear acesso por segurança
            $this->assinaturaCache = [
                'pode_acessar' => false,
                'code' => 'SUBSCRIPTION_CHECK_ERROR',
                'message' => 'Erro ao verificar assinatura. Entre em contato com o suporte.',
            ];
            return $this->assinaturaCache;
        }
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
        $this->assinatura = null;
        $this->initialized = false;
        $this->tenancyInitialized = false;
        $this->assinaturaCache = null;
        $this->bootstrapCallCount = 0;
    }
    
    /**
     * 🔥 NOVO: Limpar cache de assinatura (útil quando assinatura é criada/atualizada)
     * 
     * Força uma nova busca da assinatura na próxima verificação
     */
    public function limparCacheAssinatura(): void
    {
        $this->assinaturaCache = null;
        $this->assinatura = null; // Força nova busca também
        
        Log::debug('ApplicationContext::limparCacheAssinatura - Cache de assinatura limpo', [
            'empresa_id' => $this->empresaId,
            'user_id' => $this->user?->id,
        ]);
    }
    
    /**
     * Helper estático para acesso rápido ao serviço
     */
    public static function current(): self
    {
        return app(self::class);
    }
}
