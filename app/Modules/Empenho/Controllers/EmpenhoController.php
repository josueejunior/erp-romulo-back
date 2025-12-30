<?php

namespace App\Modules\Empenho\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Models\Processo;
use App\Modules\Empenho\Models\Empenho;
use App\Modules\Empenho\Services\EmpenhoService;
use App\Application\Empenho\UseCases\CriarEmpenhoUseCase;
use App\Application\Empenho\DTOs\CriarEmpenhoDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;
use App\Http\Requests\Empenho\EmpenhoCreateRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class EmpenhoController extends BaseApiController
{

    protected EmpenhoService $empenhoService;

    public function __construct(
        EmpenhoService $empenhoService,
        private CriarEmpenhoUseCase $criarEmpenhoUseCase,
        private ProcessoRepositoryInterface $processoRepository,
        private EmpenhoRepositoryInterface $empenhoRepository,
    ) {
        $this->empenhoService = $empenhoService;
        $this->service = $empenhoService; // Para HasDefaultActions
    }

    /**
     * API: Listar empenhos (Route::module)
     */
    public function list(Request $request)
    {
        try {
            $processoId = $request->route()->parameter('processo');
            $processoDomain = $this->processoRepository->buscarPorId($processoId);
            
            if (!$processoDomain) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Buscar modelo Eloquent via repository (DDD)
            $processo = $this->processoRepository->buscarModeloPorId($processoDomain->id);
            if (!$processo) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            return $this->index($processo);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar processo para listar empenhos', [
                'processo_id' => $request->route()->parameter('processo'),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erro ao buscar processo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API: Buscar empenho (Route::module)
     */
    public function get(Request $request)
    {
        try {
            $processoId = $request->route()->parameter('processo');
            $empenhoId = $request->route()->parameter('empenho');
            
            $processoDomain = $this->processoRepository->buscarPorId($processoId);
            if (!$processoDomain) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            $empenhoDomain = $this->empenhoRepository->buscarPorId($empenhoId);
            if (!$empenhoDomain) {
                return response()->json(['message' => 'Empenho não encontrado'], 404);
            }
            
            // Buscar modelos Eloquent via repositories (DDD)
            $processo = $this->processoRepository->buscarModeloPorId($processoDomain->id);
            $empenho = $this->empenhoRepository->buscarModeloPorId($empenhoDomain->id);
            
            if (!$processo || !$empenho) {
                return response()->json(['message' => 'Processo ou empenho não encontrado'], 404);
            }
            
            return $this->show($processo, $empenho);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar empenho', [
                'processo_id' => $request->route()->parameter('processo'),
                'empenho_id' => $request->route()->parameter('empenho'),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erro ao buscar empenho: ' . $e->getMessage()], 500);
        }
    }

    public function index(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $empenhos = $this->empenhoService->listByProcesso($processo, $empresa->id);
            return response()->json($empenhos);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * API: Criar empenho (Route::module)
     */
    public function store(Request $request)
    {
        try {
            $processoId = $request->route()->parameter('processo');
            $processoDomain = $this->processoRepository->buscarPorId($processoId);
            
            if (!$processoDomain) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Buscar modelo Eloquent via repository (DDD)
            $processo = $this->processoRepository->buscarModeloPorId($processoDomain->id);
            if (!$processo) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            return $this->storeWeb($request, $processo);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar processo para criar empenho', [
                'processo_id' => $request->route()->parameter('processo'),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erro ao buscar processo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Web: Criar empenho
     * Usa Form Request para validação
     */
    public function storeWeb(EmpenhoCreateRequest $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            // Request já está validado via Form Request
            // Preparar dados para DTO
            $data = $request->validated();
            $data['processo_id'] = $processo->id;
            $data['empresa_id'] = $empresa->id;
            
            // Usar Use Case DDD
            $dto = CriarEmpenhoDTO::fromArray($data);
            $empenhoDomain = $this->criarEmpenhoUseCase->executar($dto);
            
            // Buscar modelo Eloquent via repository (DDD)
            $empenho = $this->empenhoRepository->buscarModeloPorId($empenhoDomain->id, [
                'processo', 
                'contrato', 
                'autorizacaoFornecimento'
            ]);
            
            if (!$empenho) {
                return response()->json(['message' => 'Empenho não encontrado após criação.'], 404);
            }
            
            return response()->json($empenho, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Empenhos só podem ser criados para processos em execução.' ? 403 : 404);
        }
    }

    public function show(Processo $processo, Empenho $empenho)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $empenho = $this->empenhoService->find($processo, $empenho, $empresa->id);
            return response()->json($empenho);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * API: Atualizar empenho (Route::module)
     */
    public function update(Request $request, $id)
    {
        try {
            $processoId = $request->route()->parameter('processo');
            
            $processoDomain = $this->processoRepository->buscarPorId($processoId);
            if (!$processoDomain) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            $empenhoDomain = $this->empenhoRepository->buscarPorId($id);
            if (!$empenhoDomain) {
                return response()->json(['message' => 'Empenho não encontrado'], 404);
            }
            
            // Buscar modelos Eloquent via repositories (DDD)
            $processo = $this->processoRepository->buscarModeloPorId($processoDomain->id);
            $empenho = $this->empenhoRepository->buscarModeloPorId($empenhoDomain->id);
            
            if (!$processo || !$empenho) {
                return response()->json(['message' => 'Processo ou empenho não encontrado'], 404);
            }
            
            return $this->updateWeb($request, $processo, $empenho);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar processo/empenho para atualizar', [
                'processo_id' => $request->route()->parameter('processo'),
                'empenho_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erro ao buscar processo ou empenho: ' . $e->getMessage()], 500);
        }
    }

    /**
     * API: Excluir empenho (Route::module)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $processoId = $request->route()->parameter('processo');
            
            $processoDomain = $this->processoRepository->buscarPorId($processoId);
            if (!$processoDomain) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            $empenhoDomain = $this->empenhoRepository->buscarPorId($id);
            if (!$empenhoDomain) {
                return response()->json(['message' => 'Empenho não encontrado'], 404);
            }
            
            // Buscar modelos Eloquent via repositories (DDD)
            $processo = $this->processoRepository->buscarModeloPorId($processoDomain->id);
            $empenho = $this->empenhoRepository->buscarModeloPorId($empenhoDomain->id);
            
            if (!$processo || !$empenho) {
                return response()->json(['message' => 'Processo ou empenho não encontrado'], 404);
            }
            
            return $this->destroyWeb($processo, $empenho);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar processo/empenho para deletar', [
                'processo_id' => $request->route()->parameter('processo'),
                'empenho_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erro ao buscar processo ou empenho: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Web: Atualizar empenho
     */
    public function updateWeb(Request $request, Processo $processo, Empenho $empenho)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $empenho = $this->empenhoService->update($processo, $empenho, $request->all(), $empresa->id);
            return response()->json($empenho);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Web: Excluir empenho
     */
    public function destroyWeb(Processo $processo, Empenho $empenho)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->empenhoService->delete($processo, $empenho, $empresa->id);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }
}

