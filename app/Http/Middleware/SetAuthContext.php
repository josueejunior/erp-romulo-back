<?php

namespace App\Http\Middleware;

use App\Contracts\IAuthIdentity;
use App\Services\AuthIdentityService;
use App\Contracts\ApplicationContextContract;
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
    protected ApplicationContextContract $context;

    public function __construct(
        AuthIdentityService $authIdentityService,
        ApplicationContextContract $context
    ) {
        $this->authIdentityService = $authIdentityService;
        $this->context = $context;
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

        // 🔥 CORREÇÃO: Fazer bootstrap do ApplicationContext aqui mesmo
        // Isso garante que o tenancy seja inicializado antes de continuar
        \Log::info('SetAuthContext::handle - Chamando context->bootstrap()');
        $startTime = microtime(true);
        try {
            $this->context->bootstrap($request);
            $elapsedTime = microtime(true) - $startTime;
            \Log::info('SetAuthContext::handle - context->bootstrap() concluído', [
                'elapsed_time' => round($elapsedTime, 3) . 's',
            ]);
        } catch (\Exception $e) {
            \Log::error('SetAuthContext::handle - Erro no bootstrap', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }

        \Log::debug('SetAuthContext::handle - Chamando $next($request)');
        $startTime = microtime(true);
        $response = $next($request);
        $elapsedTime = microtime(true) - $startTime;
        
        \Log::info('SetAuthContext::handle - ✅ FIM (DEPOIS DE $next)', [
            'status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
            'elapsed_time' => round($elapsedTime, 3) . 's',
        ]);

        return $response;
    }
}


