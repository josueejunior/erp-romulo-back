<?php

namespace App\Modules\NotaFiscal\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Models\Processo;
use App\Models\NotaFiscal;
use App\Modules\NotaFiscal\Services\NotaFiscalService;
use App\Application\NotaFiscal\UseCases\CriarNotaFiscalUseCase;
use App\Application\NotaFiscal\DTOs\CriarNotaFiscalDTO;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotaFiscalController extends BaseApiController
{

    protected NotaFiscalService $notaFiscalService;

    public function __construct(
        NotaFiscalService $notaFiscalService,
        private CriarNotaFiscalUseCase $criarNotaFiscalUseCase,
    ) {
        $this->notaFiscalService = $notaFiscalService;
        $this->service = $notaFiscalService; // Para HasDefaultActions
    }

    /**
     * API: Listar notas fiscais (Route::module)
     */
    public function list(Request $request)
    {
        return $this->index(Processo::findOrFail($request->route()->parameter('processo')));
    }

    /**
     * API: Buscar nota fiscal (Route::module)
     */
    public function get(Request $request)
    {
        return $this->show(
            Processo::findOrFail($request->route()->parameter('processo')),
            NotaFiscal::findOrFail($request->route()->parameter('notaFiscal'))
        );
    }

    public function index(Processo $processo)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $notasFiscais = $this->notaFiscalService->listByProcesso($processo, $empresa->id);
            return response()->json($notasFiscais);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * API: Criar nota fiscal (Route::module)
     */
    public function store(Request $request)
    {
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        
        return $this->storeWeb($request, $processo);
    }

    /**
     * Web: Criar nota fiscal
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
            $dto = CriarNotaFiscalDTO::fromArray($data);
            $notaFiscalDomain = $this->criarNotaFiscalUseCase->executar($dto);
            
            // Buscar modelo Eloquent para resposta
            $notaFiscal = NotaFiscal::findOrFail($notaFiscalDomain->id);
            $notaFiscal->load(['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']);
            
            return response()->json($notaFiscal, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Notas fiscais só podem ser criadas para processos em execução.' ? 403 : 
               ($e->getMessage() === 'Nota fiscal deve estar vinculada a um Empenho, Contrato ou Autorização de Fornecimento.' ? 400 : 404));
        }
    }

    public function show(Processo $processo, NotaFiscal $notaFiscal)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $notaFiscal = $this->notaFiscalService->find($processo, $notaFiscal, $empresa->id);
            return response()->json($notaFiscal);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * API: Atualizar nota fiscal (Route::module)
     */
    public function update(Request $request, $id)
    {
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        $notaFiscal = NotaFiscal::findOrFail($id);
        
        return $this->updateWeb($request, $processo, $notaFiscal);
    }

    /**
     * API: Excluir nota fiscal (Route::module)
     */
    public function destroy(Request $request, $id)
    {
        $route = $request->route();
        $processo = Processo::findOrFail($route->parameter('processo'));
        $notaFiscal = NotaFiscal::findOrFail($id);
        
        return $this->destroyWeb($processo, $notaFiscal);
    }

    /**
     * Web: Atualizar nota fiscal
     */
    public function updateWeb(Request $request, Processo $processo, NotaFiscal $notaFiscal)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $notaFiscal = $this->notaFiscalService->update($processo, $notaFiscal, $request->all(), $request, $empresa->id);
            return response()->json($notaFiscal);
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
     * Web: Excluir nota fiscal
     */
    public function destroyWeb(Processo $processo, NotaFiscal $notaFiscal)
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        
        try {
            $this->notaFiscalService->delete($processo, $notaFiscal, $empresa->id);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        }
    }
}

