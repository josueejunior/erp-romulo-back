<?php

namespace App\Modules\AutorizacaoFornecimento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\ResolvesContext;
use App\Modules\Processo\Models\Processo;
use App\Modules\AutorizacaoFornecimento\Models\AutorizacaoFornecimento;
use App\Modules\AutorizacaoFornecimento\Services\AutorizacaoFornecimentoService;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\AutorizacaoFornecimento\Repositories\AutorizacaoFornecimentoRepositoryInterface;
use App\Domain\Exceptions\AutorizacaoFornecimentoNaoEncontradaException;
use App\Domain\Exceptions\AutorizacaoFornecimentoPossuiEmpenhosException;
use App\Domain\Exceptions\ProcessoNaoEmExecucaoException;
use App\Domain\Exceptions\EntidadeNaoPertenceException;
use App\Domain\Exceptions\NotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Controller para gerenciamento de Autorizações de Fornecimento
 * 
 * ✅ DDD Enterprise-Grade:
 * - Usa trait para resolver contexto (elimina repetição)
 * - Domain Exceptions específicas
 * - Controller apenas orquestra
 */
class AutorizacaoFornecimentoController extends BaseApiController
{
    use ResolvesContext;

    protected AutorizacaoFornecimentoService $afService;

    public function __construct(
        AutorizacaoFornecimentoService $afService,
        private ProcessoRepositoryInterface $processoRepository,
        private AutorizacaoFornecimentoRepositoryInterface $autorizacaoFornecimentoRepository,
    ) {
        // BaseApiController não tem construtor, não precisa chamar parent::__construct()
        $this->afService = $afService;
        $this->service = $afService; // Para HasDefaultActions
    }

    /**
     * API: Listar autorizações de fornecimento (Route::module)
     * 
     * ✅ DDD: Usa resolveProcesso para eliminar repetição
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $processo = $this->resolveProcesso($request);
            return $this->index($processo);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Buscar autorização de fornecimento (Route::module)
     * 
     * ✅ DDD: Usa resolveProcessoAutorizacao para eliminar repetição
     */
    public function get(Request $request): JsonResponse
    {
        try {
            [$processo, $autorizacao] = $this->resolveProcessoAutorizacao($request);
            return $this->show($processo, $autorizacao);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar autorizações de fornecimento de um processo
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Service
     */
    public function index(Processo $processo): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo pertence à empresa (regra de segurança)
            if ($processo->empresa_id !== $empresa->id) {
                throw new EntidadeNaoPertenceException('Processo', 'empresa ativa');
            }
            
            $afs = $this->afService->listByProcesso($processo, $empresa->id);
            return response()->json($afs);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Criar autorização de fornecimento (Route::module)
     * 
     * ✅ DDD: Usa resolveProcesso para eliminar repetição
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $processo = $this->resolveProcesso($request);
            return $this->storeWeb($request, $processo);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Web: Criar autorização de fornecimento
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Service
     */
    public function storeWeb(Request $request, Processo $processo): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo pertence à empresa (regra de segurança)
            if ($processo->empresa_id !== $empresa->id) {
                throw new EntidadeNaoPertenceException('Processo', 'empresa ativa');
            }
            
            $af = $this->afService->store($processo, $request->all(), $empresa->id);
            return response()->json($af, 201);
        } catch (ProcessoNaoEmExecucaoException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Fallback para mensagens de string (será removido quando Service usar Domain Exceptions)
            $statusCode = str_contains($e->getMessage(), 'em execução') ? 403 : 404;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * Obter autorização de fornecimento específica
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Service
     */
    public function show(Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo pertence à empresa (regra de segurança)
            if ($processo->empresa_id !== $empresa->id) {
                throw new EntidadeNaoPertenceException('Processo', 'empresa ativa');
            }
            
            // Validar que a autorização pertence à empresa e ao processo
            if ($autorizacaoFornecimento->empresa_id !== $empresa->id) {
                throw new AutorizacaoFornecimentoNaoEncontradaException();
            }
            
            if ($autorizacaoFornecimento->processo_id !== $processo->id) {
                throw new EntidadeNaoPertenceException('Autorização de Fornecimento', 'processo informado');
            }
            
            $af = $this->afService->find($processo, $autorizacaoFornecimento, $empresa->id);
            return response()->json($af);
        } catch (AutorizacaoFornecimentoNaoEncontradaException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * API: Atualizar autorização de fornecimento (Route::module)
     * 
     * ✅ DDD: Usa resolveProcessoAutorizacao para eliminar repetição
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            [$processo, $autorizacao] = $this->resolveProcessoAutorizacao($request);
            return $this->updateWeb($request, $processo, $autorizacao);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Excluir autorização de fornecimento (Route::module)
     * 
     * ✅ DDD: Usa resolveProcessoAutorizacao para eliminar repetição
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            [$processo, $autorizacao] = $this->resolveProcessoAutorizacao($request);
            return $this->destroyWeb($processo, $autorizacao);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Web: Atualizar autorização de fornecimento
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Service
     */
    public function updateWeb(Request $request, Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo pertence à empresa (regra de segurança)
            if ($processo->empresa_id !== $empresa->id) {
                throw new EntidadeNaoPertenceException('Processo', 'empresa ativa');
            }
            
            // Validar que a autorização pertence à empresa e ao processo
            if ($autorizacaoFornecimento->empresa_id !== $empresa->id) {
                throw new AutorizacaoFornecimentoNaoEncontradaException();
            }
            
            if ($autorizacaoFornecimento->processo_id !== $processo->id) {
                throw new EntidadeNaoPertenceException('Autorização de Fornecimento', 'processo informado');
            }
            
            $af = $this->afService->update($processo, $autorizacaoFornecimento, $request->all(), $empresa->id);
            return response()->json($af);
        } catch (AutorizacaoFornecimentoNaoEncontradaException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * Web: Excluir autorização de fornecimento
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Service
     */
    public function destroyWeb(Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo pertence à empresa (regra de segurança)
            if ($processo->empresa_id !== $empresa->id) {
                throw new EntidadeNaoPertenceException('Processo', 'empresa ativa');
            }
            
            // Validar que a autorização pertence à empresa e ao processo
            if ($autorizacaoFornecimento->empresa_id !== $empresa->id) {
                throw new AutorizacaoFornecimentoNaoEncontradaException();
            }
            
            if ($autorizacaoFornecimento->processo_id !== $processo->id) {
                throw new EntidadeNaoPertenceException('Autorização de Fornecimento', 'processo informado');
            }
            
            $this->afService->delete($processo, $autorizacaoFornecimento, $empresa->id);
            return response()->json(null, 204);
        } catch (AutorizacaoFornecimentoNaoEncontradaException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (AutorizacaoFornecimentoPossuiEmpenhosException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            // Fallback para mensagens de string (será removido quando Service usar Domain Exceptions)
            $statusCode = str_contains($e->getMessage(), 'empenhos vinculados') ? 403 : 404;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }
}

