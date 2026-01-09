<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\InputSanitizer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para sanitizar inputs de requisições
 * 
 * Remove HTML, scripts e caracteres perigosos de todos os inputs
 * IMPORTANTE: Não sanitiza senhas (serão hasheadas) e campos específicos
 */
class SanitizeInputs
{
    /**
     * Campos que não devem ser sanitizados
     */
    protected array $excludedFields = [
        'password',
        'senha',
        'password_confirmation',
        'senha_confirmation',
        'card_token', // Token do gateway de pagamento
        'dados_resposta', // Respostas de APIs externas
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Apenas sanitizar métodos que modificam dados
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        // Obter todos os inputs
        $inputs = $request->all();

        // Sanitizar inputs (exceto campos sensíveis)
        $sanitized = InputSanitizer::sanitizeArray($inputs, $this->excludedFields);

        // Substituir inputs sanitizados na request
        $request->merge($sanitized);

        return $next($request);
    }
}

