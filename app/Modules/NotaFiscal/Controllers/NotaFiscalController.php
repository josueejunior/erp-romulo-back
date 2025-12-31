<?php

namespace App\Modules\NotaFiscal\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Processo\Models\Processo;
use App\Modules\NotaFiscal\Models\NotaFiscal;
use App\Modules\NotaFiscal\Services\NotaFiscalService;
use App\Application\NotaFiscal\UseCases\CriarNotaFiscalUseCase;
use App\Application\NotaFiscal\UseCases\ListarNotasFiscaisUseCase;
use App\Application\NotaFiscal\UseCases\BuscarNotaFiscalUseCase;
use App\Application\NotaFiscal\DTOs\CriarNotaFiscalDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use App\Http\Requests\NotaFiscal\NotaFiscalCreateRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Controller para gerenciamento de Notas Fiscais
 * 
 * Refatorado para seguir DDD rigorosamente:
 * - Usa Form Requests para validação
 * - Usa Use Cases para lógica de negócio
 * - Não acessa modelos Eloquent diretamente (exceto para relacionamentos)
 * 
 * Segue o mesmo padrão do AssinaturaController e FornecedorController:
 * - Tenant ID: Obtido automaticamente via tenancy()->tenant (middleware já inicializou)
 * - Empresa ID: Obtido automaticamente via getEmpresaAtivaOrFail() que prioriza header X-Empresa-ID
 */
class NotaFiscalController extends BaseApiController
{
    use HasAuthContext;

    protected NotaFiscalService $notaFiscalService;

    public function __construct(
        NotaFiscalService $notaFiscalService, // Mantido para métodos específicos que ainda usam Service
        private CriarNotaFiscalUseCase $criarNotaFiscalUseCase,
        private ListarNotasFiscaisUseCase $listarNotasFiscaisUseCase,
        private BuscarNotaFiscalUseCase $buscarNotaFiscalUseCase,
        private ProcessoRepositoryInterface $processoRepository,
        private NotaFiscalRepositoryInterface $notaFiscalRepository,
    ) {
        $this->notaFiscalService = $notaFiscalService; // Para métodos que ainda precisam do Service
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

    /**
     * Listar notas fiscais de um processo
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados das notas fiscais da empresa ativa.
     */
    public function index(Processo $processo): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou baseado no X-Empresa-ID)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo pertence à empresa
            if ($processo->empresa_id !== $empresa->id) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Preparar filtros
            $filtros = [
                'empresa_id' => $empresa->id,
                'processo_id' => $processo->id,
            ];
            
            // Executar Use Case
            $paginado = $this->listarNotasFiscaisUseCase->executar($filtros);
            
            // Transformar para resposta
            $items = collect($paginado->items())->map(function ($notaFiscalDomain) {
                // Buscar modelo Eloquent para incluir relacionamentos
                $notaFiscalModel = $this->notaFiscalRepository->buscarModeloPorId(
                    $notaFiscalDomain->id,
                    ['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']
                );
                return $notaFiscalModel ? $notaFiscalModel->toArray() : null;
            })->filter();
            
            return response()->json([
                'data' => $items->values()->all(),
                'meta' => [
                    'current_page' => $paginado->currentPage(),
                    'last_page' => $paginado->lastPage(),
                    'per_page' => $paginado->perPage(),
                    'total' => $paginado->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar notas fiscais');
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
     * Usa Form Request para validação e Use Case para lógica de negócio
     */
    public function storeWeb(NotaFiscalCreateRequest $request, Processo $processo): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo pertence à empresa
            if ($processo->empresa_id !== $empresa->id) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Request já está validado via Form Request
            // Preparar dados para DTO
            $data = $request->validated();
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
            
            return response()->json([
                'message' => 'Nota fiscal criada com sucesso',
                'data' => $notaFiscal->toArray(),
            ], 201);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            $statusCode = $e->getMessage() === 'Notas fiscais só podem ser criadas para processos em execução.' ? 403 : 
                         ($e->getMessage() === 'Nota fiscal deve estar vinculada a um Empenho, Contrato ou Autorização de Fornecimento.' ? 400 : 400);
            return response()->json([
                'message' => $e->getMessage(),
            ], $statusCode);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar nota fiscal');
        }
    }

    /**
     * Obter nota fiscal específica
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados da nota fiscal da empresa ativa.
     */
    public function show(Processo $processo, NotaFiscal $notaFiscal): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo e nota fiscal pertencem à empresa
            if ($processo->empresa_id !== $empresa->id) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Executar Use Case
            $notaFiscalDomain = $this->buscarNotaFiscalUseCase->executar($notaFiscal->id);
            
            // Validar que a nota fiscal pertence à empresa ativa
            if ($notaFiscalDomain->empresaId !== $empresa->id) {
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }
            
            // Buscar modelo Eloquent para incluir relacionamentos
            $notaFiscalModel = $this->notaFiscalRepository->buscarModeloPorId(
                $notaFiscalDomain->id,
                ['empenho', 'contrato', 'autorizacaoFornecimento', 'fornecedor']
            );
            
            if (!$notaFiscalModel) {
                return response()->json(['message' => 'Nota fiscal não encontrada'], 404);
            }
            
            return response()->json(['data' => $notaFiscalModel->toArray()]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar nota fiscal');
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

