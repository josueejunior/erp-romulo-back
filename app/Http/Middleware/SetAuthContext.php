<?php

namespace App\Http\Middleware;

use App\Contracts\IAuthIdentity;
use App\Services\AuthIdentityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que autentica o usuário e define o contexto de autenticação
 * Similar ao SessionAuth do exemplo fornecido
 */
class SetAuthContext
{
    /**
     * Escopo padrão da API
     */
    public static string $scope = 'api-v1';

    protected AuthIdentityService $authIdentityService;

    public function __construct(AuthIdentityService $authIdentityService)
    {
        $this->authIdentityService = $authIdentityService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        \Log::info('SetAuthContext::handle - ✅ INÍCIO', [
            'path' => $request->path(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ]);
        
        $scope = $scope ?? static::$scope;

        /**
         * Verificar se o usuário está autenticado
         * O middleware auth:sanctum já faz a verificação antes, mas garantimos aqui
         */
        \Log::debug('SetAuthContext::handle - Verificando autenticação');
        if (!auth('sanctum')->check()) {
            \Log::warning('SetAuthContext::handle - Usuário não autenticado');
            return response()->json([
                'message' => 'Não autenticado. Faça login para continuar.',
            ], 401);
        }
        \Log::debug('SetAuthContext::handle - Usuário autenticado', ['user_id' => auth('sanctum')->id()]);

        /**
         * Seta um atributo com o valor do escopo para facilitar o uso
         * dentro do fluxo, especialmente em módulos ou rotas híbridas
         */
        $request->scope = $scope;

        /**
         * Cria e armazena a identidade de autenticação no container Laravel
         * Isso permite acesso padronizado em controllers, services e traits
         */
        \Log::debug('SetAuthContext::handle - Criando identidade de autenticação');
        $startTime = microtime(true);
        $identity = $this->authIdentityService->createFromRequest($request, $scope);
        $elapsedTime = microtime(true) - $startTime;
        \Log::debug('SetAuthContext::handle - Identidade criada', [
            'elapsed_time' => round($elapsedTime, 3) . 's',
        ]);
        
        app()->instance(IAuthIdentity::class, $identity);

        \Log::debug('SetAuthContext::handle - Chamando $next($request)');
        $startTime = microtime(true);
        $response = $next($request);
        $elapsedTime = microtime(true) - $startTime;
        
        \Log::info('SetAuthContext::handle - ✅ FIM', [
            'status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
            'elapsed_time' => round($elapsedTime, 3) . 's',
        ]);

        return $response;
    }
}


