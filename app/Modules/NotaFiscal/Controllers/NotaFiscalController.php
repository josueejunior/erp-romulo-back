<?php

namespace App\Modules\NotaFiscal\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\Processo\Models\Processo;
use App\Models\NotaFiscal;
use App\Modules\NotaFiscal\Services\NotaFiscalService;
use App\Application\NotaFiscal\UseCases\CriarNotaFiscalUseCase;
use App\Application\NotaFiscal\DTOs\CriarNotaFiscalDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotaFiscalController extends BaseApiController
{

    protected NotaFiscalService $notaFiscalService;

    public function __construct(
        NotaFiscalService $notaFiscalService,
        private CriarNotaFiscalUseCase $criarNotaFiscalUseCase,
        private ProcessoRepositoryInterface $processoRepository,
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
    ) {
        parent::__construct(app(\App\Domain\Empresa\Repositories\EmpresaRepositoryInterface::class), app(\App\Domain\Auth\Repositories\UserRepositoryInterface::class));
        $this->notaFiscalService = $notaFiscalService;
        $this->service = $notaFiscalService; // Para HasDefaultActions
    }

    /**
     * API: Listar notas fiscais (Route::module)
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
     * API: Buscar nota fiscal (Route::module)
     */
    public function get(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $notaFiscalId = $request->route()->parameter('notaFiscal');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $notaFiscalModel = $this->notaFiscalRepository->buscarModeloPorId($notaFiscalId);
        if (!$notaFiscalModel) {
            return response()->json(['message' => 'Nota fiscal não encontrada.'], 404);
        }
        
        return $this->show($processoModel, $notaFiscalModel);
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
        $processoId = $request->route()->parameter('processo');
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        return $this->storeWeb($request, $processoModel);
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
            
            // Buscar modelo Eloquent para resposta usando repository
            $notaFiscal = $this->notaFiscalRepository->buscarModeloPorId(
                $notaFiscalDomain->id,
                ['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']
            );
            
            if (!$notaFiscal) {
                return response()->json(['message' => 'Nota fiscal não encontrada após criação.'], 404);
            }
            
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
        $processoId = $request->route()->parameter('processo');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $notaFiscalModel = $this->notaFiscalRepository->buscarModeloPorId($id);
        if (!$notaFiscalModel) {
            return response()->json(['message' => 'Nota fiscal não encontrada.'], 404);
        }
        
        return $this->updateWeb($request, $processoModel, $notaFiscalModel);
    }

    /**
     * API: Excluir nota fiscal (Route::module)
     */
    public function destroy(Request $request, $id)
    {
        $processoId = $request->route()->parameter('processo');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $notaFiscalModel = $this->notaFiscalRepository->buscarModeloPorId($id);
        if (!$notaFiscalModel) {
            return response()->json(['message' => 'Nota fiscal não encontrada.'], 404);
        }
        
        return $this->destroyWeb($processoModel, $notaFiscalModel);
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

