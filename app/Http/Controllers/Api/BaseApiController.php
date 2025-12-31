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
     * @return Empresa Modelo Eloquent (necessário para relacionamentos)
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function getEmpresaAtivaOrFail(): Empresa
    {
        $user = auth()->user();
        
        if (!$user) {
            abort(401, 'Usuário não autenticado.');
        }
        
        $empresaRepository = app(EmpresaRepositoryInterface::class);
        $userRepository = app(UserRepositoryInterface::class);
        
        // Recarregar usuário do banco para garantir que temos empresa_ativa_id atualizado
        // Isso é importante após trocar empresa, pois o objeto $user pode estar em cache
        if (method_exists($user, 'refresh')) {
            $user->refresh();
        }
        
        $idDoBanco = $user->empresa_ativa_id;
        $idDoHeader = request()->header('X-Empresa-ID') ? (int) request()->header('X-Empresa-ID') : null;
        
        \Log::debug('BaseApiController::getEmpresaAtivaOrFail()', [
            'user_id' => $user->id,
            'user_empresa_ativa_id' => $idDoBanco,
            'x_empresa_id_header' => $idDoHeader,
            'tenant_id' => tenancy()->tenant?->id,
        ]);
        
        // 🔥 PRIORIDADE 1: Header X-Empresa-ID (fonte de verdade quando usuário troca empresa)
        // O header é atualizado imediatamente quando o usuário troca de empresa,
        // enquanto o banco pode estar desatualizado ou em cache
        if ($idDoHeader) {
            // Validar que o usuário tem acesso a essa empresa
            $userRepository = app(UserRepositoryInterface::class);
            $empresas = $userRepository->buscarEmpresas($user->id);
            $temAcesso = collect($empresas)->contains(fn($e) => $e->id === $idDoHeader);
            
            if ($temAcesso) {
                $empresaModel = $empresaRepository->buscarModeloPorId($idDoHeader);
                if ($empresaModel) {
                    \Log::debug('BaseApiController::getEmpresaAtivaOrFail() - Empresa encontrada via header X-Empresa-ID', [
                        'empresa_id' => $empresaModel->id,
                        'empresa_razao_social' => $empresaModel->razao_social,
                        'header_priorizado' => true,
                    ]);
                    return $empresaModel;
                }
            } else {
                \Log::warning('BaseApiController::getEmpresaAtivaOrFail() - Usuário não tem acesso à empresa do header', [
                    'user_id' => $user->id,
                    'empresa_id_header' => $idDoHeader,
                ]);
            }
        }
        
        // Prioridade 2: Se o usuário tem empresa_ativa_id no banco, usar ela
        if ($idDoBanco) {
            $empresaModel = $empresaRepository->buscarModeloPorId($idDoBanco);
            if ($empresaModel) {
                \Log::debug('BaseApiController::getEmpresaAtivaOrFail() - Empresa encontrada por empresa_ativa_id', [
                    'empresa_id' => $empresaModel->id,
                    'empresa_razao_social' => $empresaModel->razao_social,
                ]);
                return $empresaModel;
            } else {
                \Log::warning('BaseApiController::getEmpresaAtivaOrFail() - Empresa não encontrada pelo empresa_ativa_id', [
                    'empresa_ativa_id' => $idDoBanco,
                ]);
            }
        }
        
        // Se não tem empresa ativa definida, buscar primeira empresa do usuário via repository
        $empresas = $userRepository->buscarEmpresas($user->id);
        
        if (empty($empresas)) {
            \Log::error('BaseApiController::getEmpresaAtivaOrFail() - Usuário sem empresas', [
                'user_id' => $user->id,
            ]);
            abort(403, 'Você não tem acesso a nenhuma empresa.');
        }
        
        // Pegar primeira empresa e buscar modelo Eloquent
        $primeiraEmpresa = $empresas[0];
        $empresaModel = $empresaRepository->buscarModeloPorId($primeiraEmpresa->id);
        
        if (!$empresaModel) {
            \Log::error('BaseApiController::getEmpresaAtivaOrFail() - Modelo não encontrado após buscar empresas', [
                'user_id' => $user->id,
                'empresa_id' => $primeiraEmpresa->id,
            ]);
            abort(403, 'Erro ao buscar empresa.');
        }
        
        // Se encontrou empresa mas não estava definida como ativa, atualizar
        if ($user->empresa_ativa_id !== $empresaModel->id) {
            \Log::info('BaseApiController::getEmpresaAtivaOrFail() - Atualizando empresa_ativa_id', [
                'user_id' => $user->id,
                'empresa_ativa_id_antigo' => $user->empresa_ativa_id,
                'empresa_ativa_id_novo' => $empresaModel->id,
            ]);
            
            // Atualizar via repository
            $userRepository->atualizarEmpresaAtiva($user->id, $empresaModel->id);
        }
        
        \Log::debug('BaseApiController::getEmpresaAtivaOrFail() - Retornando empresa', [
            'empresa_id' => $empresaModel->id,
            'empresa_razao_social' => $empresaModel->razao_social,
        ]);
        
        return $empresaModel;
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

