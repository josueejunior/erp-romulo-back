<?php

namespace App\Services;

use App\Contracts\IAuthIdentity;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Models\Tenant;
use App\Models\Empresa;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Service para criar e gerenciar identidade de autenticação
 * 
 * 🔥 ARQUITETURA LIMPA: Usa TenantRepository para buscar tenants
 */
class AuthIdentityService
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Criar identidade a partir da requisição
     */
    public function createFromRequest(Request $request, string $scope = 'api-v1'): IAuthIdentity
    {
        $user = $request->user();
        
        if (!$user) {
            return new NullAuthIdentity($scope);
        }

        // Verificar se é admin central
        if ($user instanceof AdminUser) {
            return new AdminAuthIdentity($user, $scope);
        }

        // Usuário do tenant
        if ($user instanceof User) {
            return new TenantAuthIdentity($user, $request, $scope, $this->tenantRepository);
        }

        return new NullAuthIdentity($scope);
    }

    /**
     * Criar identidade a partir do guard
     */
    public function createFromGuard(string $guard = 'sanctum', string $scope = 'api-v1'): IAuthIdentity
    {
        $user = Auth::guard($guard)->user();
        
        if (!$user) {
            return new NullAuthIdentity($scope);
        }

        $request = request();
        
        if ($user instanceof AdminUser) {
            return new AdminAuthIdentity($user, $scope);
        }

        if ($user instanceof User) {
            return new TenantAuthIdentity($user, $request, $scope, $this->tenantRepository);
        }

        return new NullAuthIdentity($scope);
    }
}

/**
 * Implementação para usuário do tenant
 * 
 * 🔥 ARQUITETURA LIMPA: Usa TenantRepository para buscar tenant
 */
class TenantAuthIdentity implements IAuthIdentity
{
    protected User $user;
    protected ?Tenant $tenant = null;
    protected ?Empresa $empresa = null;
    protected string $scope;

    public function __construct(
        User $user, 
        Request $request, 
        string $scope = 'api-v1',
        ?TenantRepositoryInterface $tenantRepository = null
    ) {
        \Log::debug('TenantAuthIdentity::__construct - INÍCIO', [
            'user_id' => $user->id,
            'scope' => $scope,
        ]);
        
        $this->user = $user;
        $this->scope = $scope;
        
        // Obter tenant_id de múltiplas fontes
        \Log::debug('TenantAuthIdentity::__construct - Resolvendo tenant_id');
        $tenantId = $request->header('X-Tenant-ID')
            ?? $this->getTenantIdFromToken($request)
            ?? null;
        \Log::debug('TenantAuthIdentity::__construct - tenant_id resolvido', ['tenant_id' => $tenantId]);

        if ($tenantId) {
            \Log::debug('TenantAuthIdentity::__construct - Buscando tenant', ['tenant_id' => $tenantId]);
            $startTime = microtime(true);
            // 🔥 ARQUITETURA LIMPA: Usar TenantRepository em vez de Eloquent direto
            if ($tenantRepository) {
                $tenantDomain = $tenantRepository->buscarPorId($tenantId);
                if ($tenantDomain) {
                    $this->tenant = $tenantRepository->buscarModeloPorId($tenantId);
                }
            } else {
                // Fallback para compatibilidade (não recomendado)
                $this->tenant = Tenant::find($tenantId);
            }
            $elapsedTime = microtime(true) - $startTime;
            \Log::debug('TenantAuthIdentity::__construct - Tenant buscado', [
                'elapsed_time' => round($elapsedTime, 3) . 's',
                'tenant_found' => $this->tenant !== null,
            ]);
        }

        // Obter empresa ativa (mantém Eloquent direto porque precisa do relacionamento)
        // Isso é aceitável porque é infraestrutura de autenticação e precisa do modelo
        \Log::debug('TenantAuthIdentity::__construct - Resolvendo empresa ativa', [
            'empresa_ativa_id' => $this->user->empresa_ativa_id,
        ]);
        $startTime = microtime(true);
        if ($this->user->empresa_ativa_id) {
            $this->empresa = Empresa::find($this->user->empresa_ativa_id);
        } else {
            $this->empresa = $this->user->empresas()->first();
            if ($this->empresa && $this->user->empresa_ativa_id !== $this->empresa->id) {
                $this->user->empresa_ativa_id = $this->empresa->id;
                $this->user->save();
            }
        }
        $elapsedTime = microtime(true) - $startTime;
        \Log::debug('TenantAuthIdentity::__construct - Empresa resolvida', [
            'elapsed_time' => round($elapsedTime, 3) . 's',
            'empresa_id' => $this->empresa?->id,
        ]);
        
        \Log::debug('TenantAuthIdentity::__construct - FIM');
    }

    public function getUserId(): ?int
    {
        return $this->user->id;
    }

    public function getTenantId(): ?string
    {
        return $this->tenant?->id;
    }

    public function getEmpresaId(): ?int
    {
        return $this->empresa?->id;
    }

    public function getUser(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return $this->user;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function isAdminCentral(): bool
    {
        return false;
    }

    public function isTenantUser(): bool
    {
        return true;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    protected function getTenantIdFromToken(Request $request): ?string
    {
        // 🔥 JWT STATELESS: Obter tenant_id do payload JWT
        if ($request->attributes->has('auth')) {
            $payload = $request->attributes->get('auth');
            return $payload['tenant_id'] ?? null;
        }
        
        // Fallback: tentar obter do header
        $tenantId = $request->header('X-Tenant-ID');
        return $tenantId ? (string) $tenantId : null;
    }
}

/**
 * Implementação para admin central
 */
class AdminAuthIdentity implements IAuthIdentity
{
    protected AdminUser $user;
    protected string $scope;

    public function __construct(AdminUser $user, string $scope = 'admin')
    {
        $this->user = $user;
        $this->scope = $scope;
    }

    public function getUserId(): ?int
    {
        return $this->user->id;
    }

    public function getTenantId(): ?string
    {
        return null; // Admin não tem tenant
    }

    public function getEmpresaId(): ?int
    {
        return null; // Admin não tem empresa
    }

    public function getUser(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return $this->user;
    }

    public function getTenant(): ?Tenant
    {
        return null;
    }

    public function getEmpresa(): ?Empresa
    {
        return null;
    }

    public function isAdminCentral(): bool
    {
        return true;
    }

    public function isTenantUser(): bool
    {
        return false;
    }

    public function getScope(): string
    {
        return $this->scope;
    }
}

/**
 * Implementação nula (usuário não autenticado)
 */
class NullAuthIdentity implements IAuthIdentity
{
    protected string $scope;

    public function __construct(string $scope = 'api-v1')
    {
        $this->scope = $scope;
    }

    public function getUserId(): ?int
    {
        return null;
    }

    public function getTenantId(): ?string
    {
        return null;
    }

    public function getEmpresaId(): ?int
    {
        return null;
    }

    public function getUser(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return null;
    }

    public function getTenant(): ?Tenant
    {
        return null;
    }

    public function getEmpresa(): ?Empresa
    {
        return null;
    }

    public function isAdminCentral(): bool
    {
        return false;
    }

    public function isTenantUser(): bool
    {
        return false;
    }

    public function getScope(): string
    {
        return $this->scope;
    }
}

