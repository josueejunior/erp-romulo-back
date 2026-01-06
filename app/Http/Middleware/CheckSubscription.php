<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;
use App\Application\Assinatura\UseCases\VerificarAssinaturaAtivaUseCase;
use Illuminate\Support\Facades\Auth;

class CheckSubscription
{
    public function __construct(
        private VerificarAssinaturaAtivaUseCase $verificarAssinaturaAtivaUseCase,
    ) {}

    /**
     * Handle an incoming request.
     * 
     * 🔥 IMPORTANTE: Busca o tenant correto baseado na empresa ativa do usuário,
     * não apenas o tenant do header (que pode estar desatualizado).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 🔥 CRÍTICO: Buscar tenant correto baseado na empresa ativa
        // Isso garante que mesmo se o header X-Tenant-ID estiver desatualizado,
        // ainda busquemos a assinatura no tenant correto da empresa ativa
        $tenant = $this->getTenantCorretoDaEmpresaAtiva($request);

        if (!$tenant) {
            // Se não conseguir determinar o tenant, tentar usar o do header como fallback
            $tenantId = $request->header('X-Tenant-ID') 
                ?? $request->input('tenant_id')
                ?? tenancy()->tenant?->id;

            if (!$tenantId) {
                return response()->json([
                    'message' => 'Tenant ID não fornecido.',
                    'code' => 'TENANT_ID_REQUIRED'
                ], 400);
            }

            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                return response()->json([
                    'message' => 'Tenant não encontrado.',
                    'code' => 'TENANT_NOT_FOUND'
                ], 404);
            }
        }

        // Verificar assinatura usando Use Case DDD
        $resultado = $this->verificarAssinaturaAtivaUseCase->executar($tenant->id);

        // Se não pode acessar, retornar erro
        if (!$resultado['pode_acessar']) {
            return response()->json([
                'message' => $resultado['message'],
                'code' => $resultado['code'],
                'action' => $resultado['action'] ?? null,
                'data_vencimento' => $resultado['data_vencimento'] ?? null,
                'dias_expirado' => $resultado['dias_expirado'] ?? null,
            ], 403);
        }

        // Se pode acessar mas tem warning (grace period), adicionar headers
        if (isset($resultado['warning']) && $resultado['warning']) {
            return $next($request)->withHeaders([
                'X-Subscription-Warning' => 'true',
                'X-Subscription-Expired-Days' => $resultado['warning']['dias_expirado'] ?? 0,
            ]);
        }

        // Tudo OK, permitir acesso
        return $next($request);
    }

    /**
     * Busca o tenant correto baseado na empresa ativa do usuário
     * 
     * 🔥 CRÍTICO: Este método garante que sempre busquemos a assinatura no tenant correto,
     * mesmo se o header X-Tenant-ID estiver desatualizado.
     * 
     * Prioridades:
     * 1. Verificar se empresa ativa existe no tenant atual (otimização)
     * 2. Buscar empresa em outros tenants (se não encontrou no atual)
     * 3. Tenant do header X-Tenant-ID (fallback)
     * 4. Tenant do contexto tenancy (último recurso)
     * 
     * @param Request $request
     * @return Tenant|null
     */
    protected function getTenantCorretoDaEmpresaAtiva(Request $request): ?Tenant
    {
        try {
            // Obter usuário autenticado
            $user = Auth::user();
            if (!$user) {
                \Log::debug('CheckSubscription::getTenantCorretoDaEmpresaAtiva() - Usuário não autenticado');
                return null;
            }

            // Obter empresa ativa do usuário
            $empresaAtivaId = $user->empresa_ativa_id;
            if (!$empresaAtivaId) {
                \Log::debug('CheckSubscription::getTenantCorretoDaEmpresaAtiva() - Empresa ativa não definida', [
                    'user_id' => $user->id,
                ]);
                return null;
            }

            // Prioridade 1: Verificar se empresa existe no tenant atual (otimização)
            $tenantAtual = tenancy()->tenant;
            if ($tenantAtual && tenancy()->initialized) {
                try {
                    $empresaNoTenantAtual = \App\Models\Empresa::find($empresaAtivaId);
                    if ($empresaNoTenantAtual) {
                        \Log::debug('CheckSubscription::getTenantCorretoDaEmpresaAtiva() - Empresa encontrada no tenant atual', [
                            'empresa_id' => $empresaAtivaId,
                            'tenant_id' => $tenantAtual->id,
                        ]);
                        return $tenantAtual;
                    }
                } catch (\Exception $e) {
                    \Log::debug('CheckSubscription::getTenantCorretoDaEmpresaAtiva() - Erro ao buscar no tenant atual', [
                        'tenant_id' => $tenantAtual->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Prioridade 2: Buscar empresa em outros tenants (se não encontrou no atual)
            $allTenants = Tenant::all();
            foreach ($allTenants as $tenant) {
                // Pular o tenant atual (já verificamos)
                if ($tenantAtual && $tenant->id === $tenantAtual->id) {
                    continue;
                }
                
                try {
                    tenancy()->initialize($tenant);
                    $empresa = \App\Models\Empresa::find($empresaAtivaId);
                    
                    if ($empresa) {
                        // Encontrou a empresa neste tenant - este é o tenant correto
                        tenancy()->end();
                        
                        \Log::info('CheckSubscription::getTenantCorretoDaEmpresaAtiva() - Tenant encontrado via empresa em outro tenant', [
                            'empresa_id' => $empresaAtivaId,
                            'tenant_id_encontrado' => $tenant->id,
                            'tenant_razao_social' => $tenant->razao_social,
                            'tenant_atual_anterior' => $tenantAtual?->id,
                        ]);
                        
                        return $tenant;
                    }
                    
                    tenancy()->end();
                } catch (\Exception $e) {
                    tenancy()->end();
                    \Log::debug('CheckSubscription::getTenantCorretoDaEmpresaAtiva() - Erro ao buscar no tenant', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::debug('CheckSubscription::getTenantCorretoDaEmpresaAtiva() - Erro ao buscar tenant via empresa', [
                'error' => $e->getMessage(),
            ]);
        }
        
        // Prioridade 3: Fallback para tenant do header/contexto
        $tenantId = $request->header('X-Tenant-ID') 
            ?? $request->input('tenant_id')
            ?? tenancy()->tenant?->id;
        
        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                \Log::debug('CheckSubscription::getTenantCorretoDaEmpresaAtiva() - Usando tenant do header/contexto (fallback)', [
                    'tenant_id' => $tenant->id,
                ]);
                return $tenant;
            }
        }
        
        \Log::warning('CheckSubscription::getTenantCorretoDaEmpresaAtiva() - Nenhum tenant encontrado');
        return null;
    }
}

