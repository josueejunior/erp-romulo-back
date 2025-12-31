<?php

namespace App\Modules\Orgao\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Application\Orgao\Resources\OrgaoResource;
use App\Application\Orgao\UseCases\ListarOrgaosUseCase;
use App\Application\Orgao\UseCases\BuscarOrgaoUseCase;
use App\Application\Orgao\UseCases\CriarOrgaoUseCase;
use App\Application\Orgao\UseCases\AtualizarOrgaoUseCase;
use App\Application\Orgao\UseCases\DeletarOrgaoUseCase;
use App\Application\Orgao\DTOs\CriarOrgaoDTO;
use App\Application\Orgao\DTOs\AtualizarOrgaoDTO;
use App\Http\Requests\Orgao\OrgaoCreateRequest;
use App\Http\Requests\Orgao\OrgaoUpdateRequest;
use App\Helpers\PermissionHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DomainException;

/**
 * Controller para gerenciamento de Órgãos
 * 
 * Refatorado para seguir DDD rigorosamente:
 * - Usa Form Requests para validação
 * - Usa Use Cases para lógica de negócio
 * - Usa Resources para transformação
 * - Não acessa modelos Eloquent diretamente
 * - Não contém lógica de infraestrutura (cache, etc.)
 * 
 * Segue o mesmo padrão do AssinaturaController e FornecedorController:
 * - Tenant ID: Obtido automaticamente via tenancy()->tenant (middleware já inicializou)
 * - Empresa ID: Obtido automaticamente via getEmpresaAtivaOrFail() que prioriza header X-Empresa-ID
 */
class OrgaoController extends BaseApiController
{
    use HasAuthContext;

    public function __construct(
        private ListarOrgaosUseCase $listarOrgaosUseCase,
        private BuscarOrgaoUseCase $buscarOrgaoUseCase,
        private CriarOrgaoUseCase $criarOrgaoUseCase,
        private AtualizarOrgaoUseCase $atualizarOrgaoUseCase,
        private DeletarOrgaoUseCase $deletarOrgaoUseCase,
        private OrgaoResource $orgaoResource,
    ) {}

    /**
     * Listar órgãos
     * Retorna entidades de domínio transformadas via Resource
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados dos órgãos da empresa ativa.
     */
    public function list(Request $request): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou baseado no X-Empresa-ID)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Preparar filtros
            $filtros = $request->all();
            $filtros['empresa_id'] = $empresa->id;
            
            // Adicionar busca se houver
            if ($request->has('search')) {
                $filtros['search'] = $request->input('search');
            }
            
            // Executar Use Case
            $paginado = $this->listarOrgaosUseCase->executar($filtros);
            
            // Transformar entidades de domínio em arrays via Resource
            $items = collect($paginado->items())->map(fn($orgao) => 
                $this->orgaoResource->toArray($orgao)
            );
            
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
            return $this->handleException($e, 'Erro ao listar órgãos');
        }
    }

    /**
     * Obter órgão específico
     * Retorna entidade de domínio transformada via Resource
     */
    public function get(int $id): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Executar Use Case
            $orgaoDomain = $this->buscarOrgaoUseCase->executar($id);
            
            // Validar que o órgão pertence à empresa ativa
            if ($orgaoDomain->empresaId !== $empresa->id) {
                return response()->json(['message' => 'Órgão não encontrado'], 404);
            }
            
            // Transformar entidade em array via Resource
            $data = $this->orgaoResource->toArray($orgaoDomain);
            
            return response()->json(['data' => $data]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar órgão');
        }
    }

    /**
     * Criar órgão
     * Usa Form Request para validação e Use Case para lógica de negócio
     */
    public function store(OrgaoCreateRequest $request): JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para cadastrar órgãos.'], 403);
        }

        try {
            // Obter empresa automaticamente (middleware já inicializou)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Criar DTO a partir do request validado
            $validated = $request->validated();
            $validated['empresa_id'] = $empresa->id;
            
            $dto = CriarOrgaoDTO::fromArray($validated);
            
            // Executar Use Case (contém toda a lógica de negócio)
            $orgaoDomain = $this->criarOrgaoUseCase->executar($dto);
            
            // Transformar entidade em array via Resource
            $data = $this->orgaoResource->toArray($orgaoDomain);
            
            return response()->json([
                'message' => 'Órgão criado com sucesso',
                'data' => $data,
            ], 201);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar órgão');
        }
    }

    /**
     * Atualizar órgão
     * Usa Form Request para validação e Use Case para lógica de negócio
     */
    public function update(OrgaoUpdateRequest $request, int $id): JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para editar órgãos.'], 403);
        }

        try {
            // Obter empresa automaticamente (middleware já inicializou)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Request já está validado via Form Request
            $dto = AtualizarOrgaoDTO::fromRequest($request, $id);

            // Executar Use Case
            $orgaoDomain = $this->atualizarOrgaoUseCase->executar($dto, $empresa->id);
            
            // Transformar entidade em array via Resource
            $data = $this->orgaoResource->toArray($orgaoDomain);
            
            return response()->json([
                'message' => 'Órgão atualizado com sucesso',
                'data' => $data,
            ]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar órgão');
        }
    }

    /**
     * Deletar órgão
     */
    public function destroy(int $id): JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para excluir órgãos.'], 403);
        }

        try {
            // Obter empresa automaticamente (middleware já inicializou)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $this->deletarOrgaoUseCase->executar($id, $empresa->id);
            
            return response()->json(['message' => 'Órgão deletado com sucesso'], 204);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao deletar órgão');
        }
    }

    /**
     * Método index para compatibilidade com rotas Laravel padrão
     */
    public function index(Request $request): JsonResponse
    {
        return $this->list($request);
    }

    /**
     * Método show para compatibilidade com rotas Laravel padrão
     */
    public function show(int $id): JsonResponse
    {
        return $this->get($id);
    }
}
