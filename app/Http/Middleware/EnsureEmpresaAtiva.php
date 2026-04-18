<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmpresaAtiva
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            // Se for API, retornar JSON. Se for web, redirecionar para /login (URL direta)
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Usuário não autenticado.',
                ], 401);
            }
            return redirect('/login');
        }

        $user = auth()->user();

        // Se o usuário tem apenas uma empresa, define automaticamente
        if (!$user->empresa_ativa_id) {
            $empresas = $user->empresas;
            
            if ($empresas->count() === 1) {
                $user->empresa_ativa_id = $empresas->first()->id;
                $user->save();
            } elseif ($empresas->count() > 1) {
                // Se tem múltiplas empresas, redireciona para seleção
                if (!$request->routeIs('empresas.selecionar') && !$request->routeIs('empresas.definir')) {
                    return redirect()->route('empresas.selecionar');
                }
            } else {
                // Usuário sem empresas
                abort(403, 'Você não tem acesso a nenhuma empresa.');
            }
        }

        return $next($request);
    }
}
