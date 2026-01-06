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
     * 🔥 IMPORTANTE: Valida assinatura baseada no USUÁRIO autenticado,
     * não no tenant ou empresa do header (que podem estar desatualizados).
     * 
     * Fluxo:
     * 1. Obtém usuário autenticado
     * 2. Obtém empresa ativa do usuário
     * 3. Busca tenant onde essa empresa está
     * 4. Verifica assinatura desse tenant
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 🔥 CRÍTICO: Validar assinatura baseada no USUÁRIO
        // O usuário é a fonte de verdade, não o tenant/empresa do header
        $user = Auth::user();
        
        if (!$user) {
            // Se não tem usuário autenticado, permitir acesso (outros middlewares vão tratar)
            return $next($request);
        }

        // Buscar tenant correto baseado na empresa ativa do USUÁRIO
        $tenant = $this->getTenantDoUsuario($user);

        if (!$tenant) {
            \Log::warning('CheckSubscription - Não foi possível determinar tenant do usuário', [
                'user_id' => $user->id,
                'empresa_ativa_id' => $user->empresa_ativa_id,
            ]);
            
            return response()->json([
                'message' => 'Não foi possível determinar sua assinatura. Verifique se você tem uma empresa ativa.',
                'code' => 'SUBSCRIPTION_NOT_FOUND'
            ], 403);
        }

        \Log::info('CheckSubscription - Validando assinatura do usuário', [
            'user_id' => $user->id,
            'empresa_ativa_id' => $user->empresa_ativa_id,
            'tenant_id' => $tenant->id,
        ]);

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
     * Busca o tenant correto baseado no USUÁRIO autenticado
     * 
     * 🔥 CRÍTICO: A validação de assinatura é baseada no USUÁRIO, não no tenant/empresa do header.
     * 
     * Fluxo:
     * 1. Obtém empresa ativa do usuário (user->empresa_ativa_id)
     * 2. Busca tenant onde essa empresa está
     * 3. Retorna tenant para verificação de assinatura
     * 
     * @param \Illuminate\Contracts\Auth\Authenticatable $user
     * @return Tenant|null
     */
    protected function getTenantDoUsuario($user): ?Tenant
    {
        try {
            // Obter empresa ativa do usuário (fonte de verdade)
            $empresaAtivaId = $user->empresa_ativa_id;
            if (!$empresaAtivaId) {
                \Log::debug('CheckSubscription::getTenantDoUsuario() - Usuário não tem empresa ativa definida', [
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
                        \Log::info('CheckSubscription::getTenantDoUsuario() - Empresa do usuário encontrada no tenant atual', [
                            'user_id' => $user->id,
                            'empresa_id' => $empresaAtivaId,
                            'tenant_id' => $tenantAtual->id,
                        ]);
                        return $tenantAtual;
                    }
                } catch (\Exception $e) {
                    \Log::debug('CheckSubscription::getTenantDoUsuario() - Erro ao buscar no tenant atual', [
                        'tenant_id' => $tenantAtual->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Prioridade 2: Buscar empresa em outros tenants
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
                        // Encontrou a empresa neste tenant - este é o tenant correto do usuário
                        tenancy()->end();
                        
                        \Log::info('CheckSubscription::getTenantDoUsuario() - Tenant encontrado para o usuário', [
                            'user_id' => $user->id,
                            'empresa_id' => $empresaAtivaId,
                            'tenant_id_encontrado' => $tenant->id,
                            'tenant_razao_social' => $tenant->razao_social,
                        ]);
                        
                        return $tenant;
                    }
                    
                    tenancy()->end();
                } catch (\Exception $e) {
                    tenancy()->end();
                    \Log::debug('CheckSubscription::getTenantDoUsuario() - Erro ao buscar no tenant', [
                        'tenant_id' => $tenant->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('CheckSubscription::getTenantDoUsuario() - Erro ao buscar tenant do usuário', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
        
        \Log::warning('CheckSubscription::getTenantDoUsuario() - Não foi possível encontrar tenant para o usuário', [
            'user_id' => $user->id,
            'empresa_ativa_id' => $user->empresa_ativa_id ?? null,
        ]);
        
        return null;
    }
}

