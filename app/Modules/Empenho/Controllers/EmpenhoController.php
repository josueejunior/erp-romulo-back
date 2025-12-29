<?php

namespace App\Modules\Empenho\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Models\Processo;
use App\Models\Empenho;
use App\Modules\Empenho\Services\EmpenhoService;
use App\Application\Empenho\UseCases\CriarEmpenhoUseCase;
use App\Application\Empenho\DTOs\CriarEmpenhoDTO;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmpenhoController extends BaseApiController
{

    protected EmpenhoService $empenhoService;

    public function __construct(
        EmpenhoService $empenhoService,
        private CriarEmpenhoUseCase $criarEmpenhoUseCase,
    ) {
        $this->empenhoService = $empenhoService;
        $this->service = $empenhoService; // Para HasDefaultActions
    }

    /**
     * API: Listar empenhos (Route::module)
     */
    public function list(Request $request)
    {
        return $this->index(Processo::findOrFail($request->route()->parameter('processo')));
    }

    /**
     * API: Buscar empenho (Route::module)
     */
    public function get(Request $request)
    {
        return $this->show(
            Processo::findOrFail($request->route()->parameter('processo')),
            Empenho::findOrFail($request->route()->parameter('empenho'))
        );
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
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        
        return $this->storeWeb($request, $processo);
    }

    /**
     * Web: Criar empenho
     */
    public function storeWeb(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            // Preparar dados para DTO
            $data = $request->all();
            $data['processo_id'] = $processo->id;
            $data['empresa_id'] = $empresa->id;
            
            // Usar Use Case DDD
            $dto = CriarEmpenhoDTO::fromArray($data);
            $empenhoDomain = $this->criarEmpenhoUseCase->executar($dto);
            
            // Buscar modelo Eloquent para resposta
            $empenho = Empenho::findOrFail($empenhoDomain->id);
            $empenho->load(['processo', 'contrato', 'autorizacaoFornecimento']);
            
            return response()->json($empenho, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
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
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        $empenho = Empenho::findOrFail($id);
        
        return $this->updateWeb($request, $processo, $empenho);
    }

    /**
     * API: Excluir empenho (Route::module)
     */
    public function destroy(Request $request, $id)
    {
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        $empenho = Empenho::findOrFail($id);
        
        return $this->destroyWeb($processo, $empenho);
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

