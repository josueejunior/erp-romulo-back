<?php

namespace App\Modules\Contrato\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Modules\Processo\Models\Processo;
use App\Modules\Contrato\Models\Contrato;
use App\Modules\Contrato\Services\ContratoService;
use App\Application\Contrato\UseCases\CriarContratoUseCase;
use App\Application\Contrato\UseCases\ListarContratosUseCase;
use App\Application\Contrato\UseCases\ListarTodosContratosUseCase;
use App\Application\Contrato\UseCases\BuscarContratoUseCase;
use App\Application\Contrato\DTOs\CriarContratoDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\Contrato\Repositories\ContratoRepositoryInterface;
use App\Http\Requests\Contrato\ContratoCreateRequest;
use App\Http\Controllers\Traits\ResolvesContext;
use App\Domain\Exceptions\ContratoNaoEncontradoException;
use App\Domain\Exceptions\ContratoPossuiEmpenhosException;
use App\Domain\Exceptions\EntidadeNaoPertenceException;
use App\Domain\Exceptions\NotFoundException;
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
/**
 * Controller para gerenciamento de Contratos
 * 
 * ✅ DDD Enterprise-Grade:
 * - Usa trait para resolver contexto (elimina repetição)
 * - Domain Exceptions específicas
 * - Controller apenas orquestra
 */
class ContratoController extends BaseApiController
{
    use HasAuthContext;
    use ResolvesContext;

    public function __construct(
        ContratoService $contratoService, // Mantido para métodos específicos que ainda usam Service (update, delete)
        private CriarContratoUseCase $criarContratoUseCase,
        private ListarContratosUseCase $listarContratosUseCase,
        private ListarTodosContratosUseCase $listarTodosContratosUseCase,
        private BuscarContratoUseCase $buscarContratoUseCase,
        private ProcessoRepositoryInterface $processoRepository,
        private ContratoRepositoryInterface $contratoRepository,
    ) {
        $this->contratoService = $contratoService; // Para métodos que ainda precisam do Service (update, delete)
    }

    /**
     * API: Listar contratos de um processo (Route::module)
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
            return $this->handleException($e, 'Erro ao listar contratos');
        }
    }

    /**
     * API: Buscar contrato específico (Route::module)
     * 
     * ✅ DDD: Usa resolveProcessoContrato para eliminar repetição
     */
    public function get(Request $request): JsonResponse
    {
        try {
            [$processo, $contrato] = $this->resolveProcessoContrato($request);
            return $this->show($processo, $contrato);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar contrato');
        }
    }

    /**
     * Lista todos os contratos (não apenas de um processo)
     * Com filtros, indicadores e paginação
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request com filtros
     * - Obtém empresa automaticamente via getEmpresaAtivaOrFail()
     * - Chama Use Case para listar (refatorado)
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não gerencia cache (Use Case faz isso)
     * - Não aplica filtros (Use Case faz isso)
     * - Não calcula indicadores (Use Case faz isso)
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * A empresa é obtida automaticamente via getEmpresaAtivaOrFail() que prioriza header X-Empresa-ID.
     */
    public function listarTodos(Request $request): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou baseado no X-Empresa-ID)
            $empresa = $this->getEmpresaAtivaOrFail();
            $tenantId = $this->getTenantId();
            
            // Preparar filtros
            $filters = [
                'busca' => $request->busca,
                'orgao_id' => $request->orgao_id,
                'srp' => $request->has('srp') ? $request->boolean('srp') : null,
                'situacao' => $request->situacao,
                'vigente' => $request->has('vigente') ? $request->boolean('vigente') : null,
                'vencer_em' => $request->vencer_em,
                'somente_alerta' => $request->boolean('somente_alerta'),
            ];
            
            // Executar Use Case (gerencia cache internamente)
            $response = $this->listarTodosContratosUseCase->executar(
                $filters,
                $empresa->id,
                $request->ordenacao ?? 'data_fim',
                $request->direcao ?? 'asc',
                $request->per_page ?? 15,
                $tenantId
            );

            return response()->json($response);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar contratos');
        }
    }

    /**
     * Listar contratos de um processo
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Use Case
     */
    public function index(Processo $processo): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo pertence à empresa (regra de segurança)
            if ($processo->empresa_id !== $empresa->id) {
                throw new EntidadeNaoPertenceException('Processo', 'empresa ativa');
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
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar contratos');
        }
    }

    /**
     * API: Criar contrato (Route::module)
     * 
     * ✅ DDD: Usa resolveProcesso para eliminar repetição
     */
    public function store(ContratoCreateRequest $request): JsonResponse
    {
        try {
            $processo = $this->resolveProcesso($request);
            return $this->storeWeb($request, $processo);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar contrato');
        }
    }

    /**
     * Web: Criar contrato
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Valida dados (via Form Request)
     * - Chama Use Case para criar
     * - Transforma entidade em array
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id diretamente
     * - Não acessa Tenant diretamente
     * - O sistema já injeta o contexto (tenant, empresa) via middleware
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * A empresa é obtida automaticamente via getEmpresaAtivaOrFail() que prioriza header X-Empresa-ID.
     */
    public function storeWeb(ContratoCreateRequest $request, Processo $processo): JsonResponse
    {
        // Verificar permissão usando Policy
        $this->authorize('create', [\App\Modules\Contrato\Models\Contrato::class, $processo]);

        try {
            // Request já está validado via Form Request
            // Preparar dados para DTO
            $data = $request->validated();
            $data['processo_id'] = $processo->id;
            
            // Usar Use Case DDD (contém toda a lógica de negócio, incluindo tenant)
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
     * ✅ DDD: Controller apenas orquestra, validações no Use Case
     */
    public function show(Processo $processo, Contrato $contrato): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo e contrato pertencem à empresa (regra de segurança)
            if ($processo->empresa_id !== $empresa->id) {
                throw new EntidadeNaoPertenceException('Processo', 'empresa ativa');
            }
            
            if ($contrato->empresa_id !== $empresa->id) {
                throw new ContratoNaoEncontradoException();
            }
            
            // Validar que contrato pertence ao processo
            if ($contrato->processo_id !== $processo->id) {
                throw new EntidadeNaoPertenceException('Contrato', 'processo informado');
            }
            
            // Executar Use Case
            $contratoDomain = $this->buscarContratoUseCase->executar($contrato->id);
            
            // Buscar modelo Eloquent para incluir relacionamentos
            $contratoModel = $this->contratoRepository->buscarModeloPorId(
                $contratoDomain->id,
                ['processo', 'empenhos']
            );
            
            if (!$contratoModel) {
                throw new ContratoNaoEncontradoException();
            }
            
            return response()->json(['data' => $contratoModel->toArray()]);
        } catch (ContratoNaoEncontradoException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar contrato');
        }
    }

    /**
     * API: Atualizar contrato (Route::module)
     * 
     * ✅ DDD: Usa resolveProcessoContrato para eliminar repetição
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            [$processo, $contrato] = $this->resolveProcessoContrato($request);
            return $this->updateWeb($request, $processo, $contrato);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar contrato');
        }
    }

    /**
     * API: Excluir contrato (Route::module)
     * 
     * ✅ DDD: Usa resolveProcessoContrato para eliminar repetição
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            [$processo, $contrato] = $this->resolveProcessoContrato($request);
            return $this->destroyWeb($processo, $contrato);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao excluir contrato');
        }
    }

    /**
     * Web: Atualizar contrato
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Service/Use Case
     */
    public function updateWeb(Request $request, Processo $processo, Contrato $contrato): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo pertence à empresa (regra de segurança)
            if ($processo->empresa_id !== $empresa->id) {
                throw new EntidadeNaoPertenceException('Processo', 'empresa ativa');
            }
            
            // Validar que o contrato pertence à empresa e ao processo
            if ($contrato->empresa_id !== $empresa->id) {
                throw new ContratoNaoEncontradoException();
            }
            
            if ($contrato->processo_id !== $processo->id) {
                throw new EntidadeNaoPertenceException('Contrato', 'processo informado');
            }
            
            // Verificar permissão usando Policy
            $this->authorize('update', $contrato);

            // TODO: Migrar para Use Case (AtualizarContratoUseCase)
            // Por enquanto, usando Service diretamente
            $contrato = $this->contratoService->update($processo, $contrato, $request->all(), $request, $empresa->id);
            
            // Invalidar cache de contratos após atualização
            $this->invalidarCacheContratos($empresa->id);
            
            // Buscar modelo Eloquent para incluir relacionamentos
            $contratoModel = $this->contratoRepository->buscarModeloPorId(
                $contrato->id,
                ['processo', 'empenhos']
            );
            
            return response()->json([
                'message' => 'Contrato atualizado com sucesso',
                'data' => $contratoModel ? $contratoModel->toArray() : $contrato->toArray(),
            ]);
        } catch (ContratoNaoEncontradoException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar contrato');
        }
    }

    /**
     * Web: Excluir contrato
     * 
     * ✅ DDD: Controller apenas orquestra, validações no Service/Use Case
     */
    public function destroyWeb(Processo $processo, Contrato $contrato): JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Validar que o processo pertence à empresa (regra de segurança)
            if ($processo->empresa_id !== $empresa->id) {
                throw new EntidadeNaoPertenceException('Processo', 'empresa ativa');
            }
            
            // Validar que o contrato pertence à empresa e ao processo
            if ($contrato->empresa_id !== $empresa->id) {
                throw new ContratoNaoEncontradoException();
            }
            
            if ($contrato->processo_id !== $processo->id) {
                throw new EntidadeNaoPertenceException('Contrato', 'processo informado');
            }
            
            // Verificar permissão usando Policy
            $this->authorize('delete', $contrato);

            // TODO: Migrar para Use Case (DeletarContratoUseCase)
            // Por enquanto, usando Service diretamente
            $this->contratoService->delete($processo, $contrato, $empresa->id);
            
            // Invalidar cache de contratos após exclusão
            $this->invalidarCacheContratos($empresa->id);
            
            return response()->json(['message' => 'Contrato deletado com sucesso'], 204);
        } catch (ContratoNaoEncontradoException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (ContratoPossuiEmpenhosException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (EntidadeNaoPertenceException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            // Fallback para mensagens de string (será removido quando Service usar Domain Exceptions)
            $statusCode = str_contains($e->getMessage(), 'empenhos vinculados') ? 403 : 404;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }
    
    /**
     * Invalida o cache de contratos para a empresa
     */
    private function invalidarCacheContratos(int $empresaId): void
    {
        try {
            if (RedisService::isAvailable()) {
                $tenantId = $this->getTenantId();
                
                if ($tenantId) {
                    $pattern = "contratos:{$tenantId}:{$empresaId}:*";
                    $deleted = RedisService::forgetByPattern($pattern);
                    
                    \Log::debug('ContratoController: Cache de contratos invalidado', [
                        'empresa_id' => $empresaId,
                        'tenant_id' => $tenantId,
                        'pattern' => $pattern,
                        'keys_deleted' => $deleted,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('ContratoController: Falha ao invalidar cache', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

