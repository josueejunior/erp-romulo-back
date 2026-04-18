<?php

namespace App\Modules\Fornecedor\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Application\Fornecedor\Resources\FornecedorResource;
use App\Application\Fornecedor\UseCases\ListarFornecedoresUseCase;
use App\Application\Fornecedor\UseCases\BuscarFornecedorUseCase;
use App\Application\Fornecedor\UseCases\CriarFornecedorUseCase;
use App\Application\Fornecedor\UseCases\AtualizarFornecedorUseCase;
use App\Application\Fornecedor\UseCases\DeletarFornecedorUseCase;
use App\Application\Fornecedor\DTOs\CriarFornecedorDTO;
use App\Application\Fornecedor\DTOs\AtualizarFornecedorDTO;
use App\Http\Requests\Fornecedor\FornecedorCreateRequest;
use App\Http\Requests\Fornecedor\FornecedorUpdateRequest;
use App\Services\CnpjConsultaService;
use App\Helpers\PermissionHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DomainException;

/**
 * Controller para gerenciamento de Fornecedores
 * 
 * Refatorado para seguir DDD rigorosamente:
 * - Usa Form Requests para validação
 * - Usa Use Cases para lógica de negócio
 * - Usa Resources para transformação
 * - Não acessa modelos Eloquent diretamente
 * - Não contém lógica de infraestrutura (cache, etc.)
 */
class FornecedorController extends BaseApiController
{
    use HasAuthContext;

    public function __construct(
        private ListarFornecedoresUseCase $listarFornecedoresUseCase,
        private BuscarFornecedorUseCase $buscarFornecedorUseCase,
        private CriarFornecedorUseCase $criarFornecedorUseCase,
        private AtualizarFornecedorUseCase $atualizarFornecedorUseCase,
        private DeletarFornecedorUseCase $deletarFornecedorUseCase,
        private FornecedorResource $fornecedorResource,
        private CnpjConsultaService $cnpjConsultaService,
    ) {}
    
    /**
     * Consultar CNPJ na Receita Federal
     * 
     * Retorna dados da empresa para preenchimento automático
     */
    public function consultarCnpj(Request $request): JsonResponse
    {
        $cnpj = $request->input('cnpj') ?? $request->route('cnpj');
        
        if (!$cnpj) {
            return response()->json(['message' => 'CNPJ é obrigatório'], 400);
        }
        
        if (!$this->cnpjConsultaService->validarCnpj($cnpj)) {
            return response()->json(['message' => 'CNPJ inválido'], 422);
        }
        
        $dados = $this->cnpjConsultaService->consultar($cnpj);
        
        if (!$dados) {
            return response()->json(['message' => 'CNPJ não encontrado ou serviço indisponível'], 404);
        }
        
        return response()->json([
            'data' => $dados,
        ]);
    }

    /**
     * Obtém empresa_id do contexto (automático via BaseApiController)
     * Retorna null se não conseguir obter (para permitir consulta sem empresa)
     */
    protected function getEmpresaIdOrNull(): ?int
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            return $empresa->id;
        } catch (\Exception $e) {
            \Log::debug('FornecedorController::getEmpresaIdOrNull() - Não foi possível obter empresa ativa', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Listar fornecedores
     * Retorna entidades de domínio transformadas via Resource
     * 
     * O middleware já inicializou o tenant correto baseado no X-Tenant-ID do header.
     * Apenas retorna os dados dos fornecedores da empresa ativa.
     */
    public function list(Request $request): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou baseado no X-Empresa-ID)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Preparar filtros
            $filtros = $request->all();
            $filtros['empresa_id'] = $empresa->id;
            
            // Executar Use Case
            $paginado = $this->listarFornecedoresUseCase->executar($filtros);
            
            // Transformar entidades de domínio em arrays via Resource
            $items = collect($paginado->items())->map(fn($fornecedor) => 
                $this->fornecedorResource->toArray($fornecedor)
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
            return $this->handleException($e, 'Erro ao listar fornecedores');
        }
    }

    /**
     * Obter fornecedor específico
     * Retorna entidade de domínio transformada via Resource
     */
    public function get(int $id): JsonResponse
    {
        try {
            // Obter empresa automaticamente (middleware já inicializou)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Executar Use Case
            $fornecedorDomain = $this->buscarFornecedorUseCase->executar($id);
            
            // Validar que o fornecedor pertence à empresa ativa
            if ($fornecedorDomain->empresaId !== $empresa->id) {
                return response()->json(['message' => 'Fornecedor não encontrado'], 404);
            }
            
            // Transformar entidade em array via Resource
            $data = $this->fornecedorResource->toArray($fornecedorDomain);
            
            return response()->json(['data' => $data]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar fornecedor');
        }
    }

    /**
     * Criar fornecedor
     * 
     * ✅ O QUE O CONTROLLER FAZ:
     * - Recebe request
     * - Valida dados (via Form Request)
     * - Chama um Application Service
     * 
     * ❌ O QUE O CONTROLLER NÃO FAZ:
     * - Não lê tenant_id
     * - Não acessa Tenant
     * - Não sabe se existe multi-tenant
     * - Não filtra nada por tenant_id
     */
    public function store(FornecedorCreateRequest $request): JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para cadastrar fornecedores.'], 403);
        }

        try {
            // Criar DTO a partir do request validado
            $dto = CriarFornecedorDTO::fromArray($request->validated());
            
            // Executar Use Case (contém toda a lógica de negócio, incluindo tenant)
            $fornecedorDomain = $this->criarFornecedorUseCase->executar($dto);
            
            // Transformar entidade em array via Resource
            $data = $this->fornecedorResource->toArray($fornecedorDomain);
            
            return response()->json([
                'message' => 'Fornecedor criado com sucesso',
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
            return $this->handleException($e, 'Erro ao criar fornecedor');
        }
    }

    /**
     * Atualizar fornecedor
     * Usa Form Request para validação e Use Case para lógica de negócio
     */
    public function update(FornecedorUpdateRequest $request, int $id): JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para editar fornecedores.'], 403);
        }

        try {
            // Obter empresa automaticamente (middleware já inicializou)
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Request já está validado via Form Request
            $dto = AtualizarFornecedorDTO::fromRequest($request, $id);

            // Executar Use Case
            $fornecedorDomain = $this->atualizarFornecedorUseCase->executar($dto, $empresa->id);
            
            // Transformar entidade em array via Resource
            $data = $this->fornecedorResource->toArray($fornecedorDomain);
            
            return response()->json([
                'message' => 'Fornecedor atualizado com sucesso',
                'data' => $data,
            ]);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar fornecedor');
        }
    }

    /**
     * Deletar fornecedor
     */
    public function destroy(int $id): JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para excluir fornecedores.'], 403);
        }

        try {
            $this->deletarFornecedorUseCase->executar($id);
            return response()->json(['message' => 'Fornecedor deletado com sucesso'], 204);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao deletar fornecedor');
        }
    }
}
