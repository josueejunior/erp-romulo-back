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
        $scope = $scope ?? static::$scope;

        /**
         * Faz a autenticação através do AuthGuard mapeado para uso
         * Caso não exista token ou seja inválido, irá lançar uma exceção
         */
        auth('sanctum')->check();

        /**
         * Seta um atributo com o valor do escopo para facilitar o uso
         * dentro do fluxo, especialmente em módulos ou rotas híbridas
         */
        $request->scope = $scope;

        /**
         * Cria e armazena a identidade de autenticação no container Laravel
         * Isso permite acesso padronizado em controllers, services e traits
         */
        $identity = $this->authIdentityService->createFromRequest($request, $scope);
        app()->instance(IAuthIdentity::class, $identity);

        return $next($request);
    }
}

