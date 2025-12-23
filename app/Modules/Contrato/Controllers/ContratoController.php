<?php

namespace App\Modules\Contrato\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasDefaultActions;
use App\Modules\Processo\Models\Processo;
use App\Models\Contrato;
use App\Modules\Contrato\Services\ContratoService;
use App\Services\RedisService;
use Illuminate\Http\Request;

class ContratoController extends BaseApiController
{
    use HasDefaultActions;

    protected ContratoService $contratoService;

    public function __construct(ContratoService $contratoService)
    {
        $this->contratoService = $contratoService;
        $this->service = $contratoService; // Para HasDefaultActions
    }

    /**
     * API: Listar contratos de um processo (Route::module)
     */
    public function list(Request $request)
    {
        return $this->index(Processo::findOrFail($request->route()->parameter('processo')));
    }

    /**
     * API: Buscar contrato específico (Route::module)
     */
    public function get(Request $request)
    {
        return $this->show(
            Processo::findOrFail($request->route()->parameter('processo')),
            Contrato::findOrFail($request->route()->parameter('contrato'))
        );
    }

    /**
     * Lista todos os contratos (não apenas de um processo)
     * Com filtros, indicadores e paginação
     */
    public function listarTodos(Request $request)
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $tenantId = tenancy()->tenant?->id;
            
            // Criar chave de cache baseada nos filtros
            $filters = [
                'busca' => $request->busca,
                'orgao_id' => $request->orgao_id,
                'srp' => $request->has('srp') ? $request->boolean('srp') : null,
                'situacao' => $request->situacao,
                'vigente' => $request->has('vigente') ? $request->boolean('vigente') : null,
                'vencer_em' => $request->vencer_em,
                'somente_alerta' => $request->boolean('somente_alerta'),
                'page' => $request->page ?? 1,
            ];
            $cacheKey = "contratos:{$tenantId}:{$empresa->id}:" . md5(json_encode($filters));
            
            // Tentar obter do cache
            if ($tenantId && RedisService::isAvailable()) {
                $cached = RedisService::get($cacheKey);
                if ($cached !== null) {
                    return response()->json($cached);
                }
            }
            
            $response = $this->contratoService->listarTodos(
                $filters,
                $empresa->id,
                $request->ordenacao ?? 'data_fim',
                $request->direcao ?? 'asc',
                $request->per_page ?? 15
            );

            // Salvar no cache (5 minutos)
            if ($tenantId && RedisService::isAvailable()) {
                RedisService::set($cacheKey, $response, 300);
            }

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Erro ao listar contratos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => $e->getMessage() ?: 'Erro ao listar contratos'
            ], 500);
        }
    }

    public function index(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $contratos = $this->contratoService->listByProcesso($processo, $empresa->id);
            return response()->json($contratos);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * API: Criar contrato (Route::module)
     */
    public function store(Request $request)
    {
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        
        return $this->storeWeb($request, $processo);
    }

    /**
     * Web: Criar contrato
     */
    public function storeWeb(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar permissão usando Policy
        $this->authorize('create', [\App\Models\Contrato::class, $processo]);

        try {
            $contrato = $this->contratoService->store($processo, $request->all(), $request, $empresa->id);
            return response()->json($contrato, 201);
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

    public function show(Processo $processo, Contrato $contrato)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $contrato = $this->contratoService->find($processo, $contrato, $empresa->id);
            return response()->json($contrato);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * API: Atualizar contrato (Route::module)
     */
    public function update(Request $request, $id)
    {
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        $contrato = Contrato::findOrFail($id);
        
        return $this->updateWeb($request, $processo, $contrato);
    }

    /**
     * API: Excluir contrato (Route::module)
     */
    public function destroy(Request $request, $id)
    {
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        $contrato = Contrato::findOrFail($id);
        
        return $this->destroyWeb($processo, $contrato);
    }

    /**
     * Web: Atualizar contrato
     */
    public function updateWeb(Request $request, Processo $processo, Contrato $contrato)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar permissão usando Policy
        $this->authorize('update', $contrato);

        try {
            $contrato = $this->contratoService->update($processo, $contrato, $request->all(), $request, $empresa->id);
            return response()->json($contrato);
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
     * Web: Excluir contrato
     */
    public function destroyWeb(Processo $processo, Contrato $contrato)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar permissão usando Policy
        $this->authorize('delete', $contrato);

        try {
            $this->contratoService->delete($processo, $contrato, $empresa->id);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Não é possível excluir um contrato que possui empenhos vinculados.' ? 403 : 404);
        }
    }
}

