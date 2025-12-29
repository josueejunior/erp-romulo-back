<?php

namespace App\Modules\AutorizacaoFornecimento\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Models\Processo;
use App\Models\AutorizacaoFornecimento;
use App\Modules\AutorizacaoFornecimento\Services\AutorizacaoFornecimentoService;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\AutorizacaoFornecimento\Repositories\AutorizacaoFornecimentoRepositoryInterface;
use Illuminate\Http\Request;

class AutorizacaoFornecimentoController extends BaseApiController
{

    protected AutorizacaoFornecimentoService $afService;

    public function __construct(
        AutorizacaoFornecimentoService $afService,
        private ProcessoRepositoryInterface $processoRepository,
        private AutorizacaoFornecimentoRepositoryInterface $autorizacaoFornecimentoRepository,
    ) {
        parent::__construct(app(\App\Domain\Empresa\Repositories\EmpresaRepositoryInterface::class), app(\App\Domain\Auth\Repositories\UserRepositoryInterface::class));
        $this->afService = $afService;
        $this->service = $afService; // Para HasDefaultActions
    }

    /**
     * API: Listar autorizações de fornecimento (Route::module)
     */
    public function list(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        return $this->index($processoModel);
    }

    /**
     * API: Buscar autorização de fornecimento (Route::module)
     */
    public function get(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $autorizacaoId = $request->route()->parameter('autorizacaoFornecimento');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $autorizacaoModel = $this->autorizacaoFornecimentoRepository->buscarModeloPorId($autorizacaoId);
        if (!$autorizacaoModel) {
            return response()->json(['message' => 'Autorização de fornecimento não encontrada.'], 404);
        }
        
        return $this->show($processoModel, $autorizacaoModel);
    }

    public function index(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $afs = $this->afService->listByProcesso($processo, $empresa->id);
            return response()->json($afs);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * API: Criar autorização de fornecimento (Route::module)
     */
    public function store(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        return $this->storeWeb($request, $processoModel);
    }

    /**
     * Web: Criar autorização de fornecimento
     */
    public function storeWeb(Request $request, Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $af = $this->afService->store($processo, $request->all(), $empresa->id);
            return response()->json($af, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Autorizações de Fornecimento só podem ser criadas para processos em execução.' ? 403 : 404);
        }
    }

    public function show(Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $af = $this->afService->find($processo, $autorizacaoFornecimento, $empresa->id);
            return response()->json($af);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * API: Atualizar autorização de fornecimento (Route::module)
     */
    public function update(Request $request, $id)
    {
        $processoId = $request->route()->parameter('processo');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $autorizacaoModel = $this->autorizacaoFornecimentoRepository->buscarModeloPorId($id);
        if (!$autorizacaoModel) {
            return response()->json(['message' => 'Autorização de fornecimento não encontrada.'], 404);
        }
        
        return $this->updateWeb($request, $processoModel, $autorizacaoModel);
    }

    /**
     * API: Excluir autorização de fornecimento (Route::module)
     */
    public function destroy(Request $request, $id)
    {
        $processoId = $request->route()->parameter('processo');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $autorizacaoModel = $this->autorizacaoFornecimentoRepository->buscarModeloPorId($id);
        if (!$autorizacaoModel) {
            return response()->json(['message' => 'Autorização de fornecimento não encontrada.'], 404);
        }
        
        return $this->destroyWeb($processoModel, $autorizacaoModel);
    }

    /**
     * Web: Atualizar autorização de fornecimento
     */
    public function updateWeb(Request $request, Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $af = $this->afService->update($processo, $autorizacaoFornecimento, $request->all(), $empresa->id);
            return response()->json($af);
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
     * Web: Excluir autorização de fornecimento
     */
    public function destroyWeb(Processo $processo, AutorizacaoFornecimento $autorizacaoFornecimento)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->afService->delete($processo, $autorizacaoFornecimento, $empresa->id);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Não é possível excluir uma AF que possui empenhos vinculados.' ? 403 : 404);
        }
    }
}

