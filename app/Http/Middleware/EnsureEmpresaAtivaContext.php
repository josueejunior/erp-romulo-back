<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Empresa;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para garantir que a empresa ativa está definida e disponível globalmente
 * 
 * Este middleware:
 * 1. Verifica se o usuário tem empresa_ativa_id
 * 2. Se não tem, busca primeira empresa e atualiza
 * 3. Compartilha empresa_id no contexto de logs
 * 4. Injeta empresa no container para uso em Global Scopes
 */
class EnsureEmpresaAtivaContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Apenas para rotas autenticadas
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Se for admin, não precisa de empresa
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        // Prioridade 1: Header X-Empresa-ID (quando usuário troca empresa)
        $empresaId = null;
        if ($request->header('X-Empresa-ID')) {
            $empresaIdFromHeader = (int) $request->header('X-Empresa-ID');
            
            // Verificar se o usuário tem acesso a esta empresa
            $temAcesso = $user->empresas()->where('empresas.id', $empresaIdFromHeader)->exists();
            if ($temAcesso) {
                $empresaId = $empresaIdFromHeader;
                
                // Se empresa_ativa_id do usuário for diferente, atualizar
                if ($user->empresa_ativa_id !== $empresaId) {
                    $user->empresa_ativa_id = $empresaId;
                    $user->save();
                    
                    Log::info('Empresa ativa atualizada via header X-Empresa-ID', [
                        'user_id' => $user->id,
                        'empresa_id' => $empresaId,
                    ]);
                }
            } else {
                Log::warning('Usuário tentou acessar empresa sem permissão via header', [
                    'user_id' => $user->id,
                    'empresa_id_header' => $empresaIdFromHeader,
                ]);
            }
        }
        
        // Prioridade 2: empresa_ativa_id do usuário (se header não foi fornecido)
        if (!$empresaId && $user->empresa_ativa_id) {
            $empresaId = $user->empresa_ativa_id;
            
            // Verificar se a empresa existe
            $empresa = Empresa::find($empresaId);
            if (!$empresa) {
                Log::warning('Empresa ativa não encontrada', [
                    'user_id' => $user->id,
                    'empresa_ativa_id' => $empresaId,
                ]);
                $empresaId = null;
            }
        }

        // Se não tem empresa ativa válida, buscar primeira empresa
        if (!$empresaId) {
            try {
                $empresa = $user->empresas()->first();
                if ($empresa) {
                    $empresaId = $empresa->id;
                    
                    // Atualizar empresa_ativa_id no banco
                    $user->empresa_ativa_id = $empresaId;
                    $user->save();
                    
                    Log::info('Empresa ativa atualizada automaticamente', [
                        'user_id' => $user->id,
                        'empresa_id' => $empresaId,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Erro ao buscar empresa do usuário', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Compartilhar contexto de logs (tenant_id e empresa_id)
        $tenantId = tenancy()->tenant?->id;
        Log::shareContext([
            'tenant_id' => $tenantId,
            'empresa_id' => $empresaId,
            'user_id' => $user->id,
        ]);

        // Registrar empresa_id no request para uso em Global Scopes
        if ($empresaId) {
            $request->attributes->set('empresa_id', $empresaId);
            
            // Também disponibilizar via app() para Global Scopes
            app()->instance('current_empresa_id', $empresaId);
            
            // Setar empresaId no TenantContext (para Use Cases)
            // Atualizar o contexto mesmo que já tenha sido setado antes
            $tenantId = tenancy()->tenant?->id;
            if ($tenantId) {
                \App\Domain\Shared\ValueObjects\TenantContext::set($tenantId, $empresaId);
            } elseif (\App\Domain\Shared\ValueObjects\TenantContext::has()) {
                // Se o contexto já existe mas não temos tenantId ainda, 
                // vamos obter do contexto existente e atualizar
                $context = \App\Domain\Shared\ValueObjects\TenantContext::get();
                \App\Domain\Shared\ValueObjects\TenantContext::set($context->tenantId, $empresaId);
            }
        }

        return $next($request);
    }
}

