<?php

namespace App\Modules\Contrato\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Processo\Models\Processo;
use App\Modules\Contrato\Models\Contrato;
use App\Modules\Contrato\Services\ContratoService;
use App\Application\Contrato\UseCases\CriarContratoUseCase;
use App\Application\Contrato\UseCases\ListarContratosUseCase;
use App\Application\Contrato\UseCases\BuscarContratoUseCase;
use App\Application\Contrato\DTOs\CriarContratoDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Contrato\Repositories\ContratoRepositoryInterface;
use App\Http\Requests\Contrato\ContratoCreateRequest;
use App\Services\RedisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Controller para gerenciamento de Contratos
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
class ContratoController extends BaseApiController
{
    use HasAuthContext;

    public function __construct(
        ContratoService $contratoService, // Mantido para métodos específicos que ainda usam Service
        private CriarContratoUseCase $criarContratoUseCase,
        private ListarContratosUseCase $listarContratosUseCase,
        private BuscarContratoUseCase $buscarContratoUseCase,
        private ProcessoRepositoryInterface $processoRepository,
        private ContratoRepositoryInterface $contratoRepository,
    ) {
        $this->contratoService = $contratoService; // Para métodos que ainda precisam do Service
    }

    /**
     * API: Listar contratos de um processo (Route::module)
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
     * API: Buscar contrato específico (Route::module)
     */
    public function get(Request $request)
    {
        $processoId = $request->route()->parameter('processo');
        $contratoId = $request->route()->parameter('contrato');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $contratoModel = $this->contratoRepository->buscarModeloPorId($contratoId);
        if (!$contratoModel) {
            return response()->json(['message' => 'Contrato não encontrado.'], 404);
        }
        
        return $this->show($processoModel, $contratoModel);
    }

    /**
     * Lista todos os contratos (não apenas de um processo)
     * Com filtros, indicadores e paginação
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados dos contratos da empresa ativa.
     */
    public function listarTodos(Request $request): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou baseado no X-Empresa-ID)
            $empresa = $this->getEmpresaAtivaOrFail();
            $tenantId = $this->getTenantId();
            
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

    /**
     * Listar contratos de um processo
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados dos contratos da empresa ativa.
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
            $paginado = $this->listarContratosUseCase->executar($filtros);
            
            // Transformar para resposta
            $items = collect($paginado->items())->map(function ($contratoDomain) {
                // Buscar modelo Eloquent para incluir relacionamentos
                $contratoModel = $this->contratoRepository->buscarModeloPorId(
                    $contratoDomain->id,
                    ['processo', 'empenhos']
                );
                return $contratoModel ? $contratoModel->toArray() : null;
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
            return $this->handleException($e, 'Erro ao listar contratos');
        }
    }

    /**
     * API: Criar contrato (Route::module)
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
     * Web: Criar contrato
     * Usa Form Request para validação e Use Case para lógica de negócio
     */
    public function storeWeb(ContratoCreateRequest $request, Processo $processo): JsonResponse
    {
        // Obter empresa automaticamente (middleware já inicializou)
        $empresa = $this->getEmpresaAtivaOrFail();
        
        // Verificar permissão usando Policy
        $this->authorize('create', [\App\Modules\Contrato\Models\Contrato::class, $processo]);

        try {
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
            $dto = CriarContratoDTO::fromArray($data);
            $contratoDomain = $this->criarContratoUseCase->executar($dto);
            
            // Buscar modelo Eloquent para resposta usando repository
            $contrato = $this->contratoRepository->buscarModeloPorId(
                $contratoDomain->id,
                ['processo', 'empenhos']
            );
            
            if (!$contrato) {
                return response()->json(['message' => 'Contrato não encontrado após criação.'], 404);
            }
            
            return response()->json([
                'message' => 'Contrato criado com sucesso',
                'data' => $contrato->toArray(),
            ], 201);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar contrato');
        }
    }

    /**
     * Obter contrato específico
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados do contrato da empresa ativa.
     */
    public function show(Processo $processo, Contrato $contrato): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo e contrato pertencem à empresa
            if ($processo->empresa_id !== $empresa->id) {
                return response()->json(['message' => 'Processo não encontrado'], 404);
            }
            
            // Executar Use Case
            $contratoDomain = $this->buscarContratoUseCase->executar($contrato->id);
            
            // Validar que o contrato pertence à empresa ativa
            if ($contratoDomain->empresaId !== $empresa->id) {
                return response()->json(['message' => 'Contrato não encontrado'], 404);
            }
            
            // Buscar modelo Eloquent para incluir relacionamentos
            $contratoModel = $this->contratoRepository->buscarModeloPorId(
                $contratoDomain->id,
                ['processo', 'empenhos']
            );
            
            if (!$contratoModel) {
                return response()->json(['message' => 'Contrato não encontrado'], 404);
            }
            
            return response()->json(['data' => $contratoModel->toArray()]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar contrato');
        }
    }

    /**
     * API: Atualizar contrato (Route::module)
     */
    public function update(Request $request, $id)
    {
        $processoId = $request->route()->parameter('processo');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $contratoModel = $this->contratoRepository->buscarModeloPorId($id);
        if (!$contratoModel) {
            return response()->json(['message' => 'Contrato não encontrado.'], 404);
        }
        
        return $this->updateWeb($request, $processoModel, $contratoModel);
    }

    /**
     * API: Excluir contrato (Route::module)
     */
    public function destroy(Request $request, $id)
    {
        $processoId = $request->route()->parameter('processo');
        
        $processoModel = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processoModel) {
            return response()->json(['message' => 'Processo não encontrado.'], 404);
        }
        
        $contratoModel = $this->contratoRepository->buscarModeloPorId($id);
        if (!$contratoModel) {
            return response()->json(['message' => 'Contrato não encontrado.'], 404);
        }
        
        return $this->destroyWeb($processoModel, $contratoModel);
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

