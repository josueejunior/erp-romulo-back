<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller base para APIs que precisam de empresa ativa
 * 
 * Fornece métodos auxiliares comuns para controllers de API
 * 
 * Refatorado para usar DDD (repositories)
 */
abstract class BaseApiController extends Controller
{
    /**
     * Obtém a empresa ativa do usuário autenticado ou lança exceção
     * 
     * 🔥 REFATORADO: Agora usa ApplicationContext ao invés de resolver manualmente.
     * Toda a lógica de resolução está centralizada no ApplicationContext.
     * 
     * @return Empresa Modelo Eloquent (necessário para relacionamentos)
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function getEmpresaAtivaOrFail(): Empresa
    {
        // 🔥 REGRA DE OURO: Controller NUNCA resolve empresa.
        // O ApplicationContext já resolveu tudo no middleware.
        // Se não estiver inicializado → bug de fluxo, não do controller.
        
        $context = app(\App\Contracts\ApplicationContextContract::class);
        
        if (!$context->isInitialized()) {
            \Log::error('BaseApiController::getEmpresaAtivaOrFail() - ApplicationContext não inicializado', [
                'message' => 'ApplicationContext deve ser inicializado pelo middleware antes de usar o controller',
            ]);
            abort(500, 'Erro interno: contexto não inicializado. Verifique se o middleware está configurado.');
        }
        
        try {
            return $context->empresa();
        } catch (\RuntimeException $e) {
            \Log::error('BaseApiController::getEmpresaAtivaOrFail() - Empresa não encontrada no contexto', [
                'error' => $e->getMessage(),
            ]);
            abort(403, 'Você não tem acesso a nenhuma empresa.');
        }
    }

    /**
     * Tratamento centralizado de exceções
     * 
     * @param \Exception $e
     * @param string $mensagemPadrao
     * @return JsonResponse
     */
    protected function handleException(\Exception $e, string $mensagemPadrao = 'Erro ao processar requisição'): JsonResponse
    {
        \Log::error($mensagemPadrao, [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        // DomainException retorna 400 (Bad Request)
        if ($e instanceof \App\Domain\Exceptions\DomainException) {
            $statusCode = $e->getCode() ?: 400;
            $response = ['message' => $e->getMessage()];
            
            // Adicionar contexto para BusinessRuleException
            if ($e instanceof \App\Domain\Exceptions\BusinessRuleException) {
                $response['rule'] = $e->rule;
                $response['context'] = $e->context;
            }
            
            // Adicionar erros para ValidationException
            if ($e instanceof \App\Domain\Exceptions\ValidationException && !empty($e->errors)) {
                $response['errors'] = $e->errors;
            }
            
            return response()->json($response, $statusCode);
        }

        // ValidationException retorna 422 (Unprocessable Entity)
        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        }

        // HttpException retorna o código HTTP da exceção
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        // Outras exceções retornam 500 (Internal Server Error)
        return response()->json([
            'message' => $mensagemPadrao . ': ' . $e->getMessage(),
        ], 500);
    }
}

