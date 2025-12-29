<?php

namespace App\Modules\Fornecedor\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\FornecedorResource;
use App\Modules\Fornecedor\Models\Fornecedor;
use App\Application\Fornecedor\UseCases\CriarFornecedorUseCase;
use App\Application\Fornecedor\DTOs\CriarFornecedorDTO;
use App\Domain\Fornecedor\Repositories\FornecedorRepositoryInterface;
use App\Domain\Fornecedor\Entities\Fornecedor as FornecedorDomain;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;
use App\Services\RedisService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class FornecedorController extends BaseApiController
{
    public function __construct(
        private CriarFornecedorUseCase $criarFornecedorUseCase,
        private FornecedorRepositoryInterface $fornecedorRepository,
    ) {}

    /**
     * Listar fornecedores usando Repository DDD
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $tenantId = tenancy()->tenant?->id;
        
        $filters = $request->all();
        $filters['empresa_id'] = $empresa->id;
        $cacheKey = "fornecedores:{$tenantId}:{$empresa->id}:" . md5(json_encode($filters));
        
        // Tentar obter do cache
        if ($tenantId && RedisService::isAvailable()) {
            $cached = RedisService::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }
        }

        try {
            // Usar Repository DDD
            $fornecedoresDomain = $this->fornecedorRepository->buscarComFiltros($filters);
            
            // Converter entidades de domínio para modelos Eloquent para Resource
            $fornecedores = $fornecedoresDomain->getCollection()->map(function ($fornecedorDomain) {
                return Fornecedor::findOrFail($fornecedorDomain->id);
            });
            
            // Criar paginator manual para manter estrutura
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $fornecedores,
                $fornecedoresDomain->total(),
                $fornecedoresDomain->perPage(),
                $fornecedoresDomain->currentPage(),
                ['path' => $request->url(), 'query' => $request->query()]
            );
            
            $response = FornecedorResource::collection($paginator);
            $responseData = $response->response()->getData(true);
            
            // Adicionar paginação
            $responseData = [
                'data' => $responseData['data'] ?? $responseData,
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from' => $paginator->firstItem(),
                    'last_page' => $paginator->lastPage(),
                    'path' => $paginator->path(),
                    'per_page' => $paginator->perPage(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ],
            ];

            // Salvar no cache (5 minutos)
            if ($tenantId && RedisService::isAvailable()) {
                RedisService::set($cacheKey, $responseData, 300);
            }

            return response()->json($responseData);
        } catch (\Exception $e) {
            Log::error('Erro ao listar fornecedores', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Buscar fornecedor por ID usando Repository DDD
     */
    public function show(Fornecedor $fornecedor): \Illuminate\Http\JsonResponse
    {
        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $fornecedorDomain = $this->fornecedorRepository->buscarPorId($fornecedor->id);
            
            if (!$fornecedorDomain || $fornecedorDomain->empresaId !== $empresa->id) {
                return response()->json(['message' => 'Fornecedor não encontrado.'], 404);
            }
            
            return response()->json(['data' => new FornecedorResource($fornecedor)]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Sobrescrever handleStore para validação de permissão e usar FornecedorResource
     */
    protected function handleStore(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para cadastrar fornecedores.',
            ], 403);
        }

        try {
            $data = array_merge($request->all(), $mergeParams);
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Garantir que empresa_id está definido
            if (!isset($data['empresa_id'])) {
                $data['empresa_id'] = $empresa->id;
            }
            
            // Validar dados
            $validator = $this->service->validateStoreData($data);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Usar Use Case DDD
            $dto = CriarFornecedorDTO::fromArray($validator->validated());
            $fornecedorDomain = $this->criarFornecedorUseCase->executar($dto);
            
            // Buscar modelo Eloquent para Resource
            $fornecedor = Fornecedor::findOrFail($fornecedorDomain->id);
            
            // Debug: Log após criar
            \Log::debug('FornecedorController->handleStore() criado', [
                'fornecedor_id' => $fornecedor->id,
                'fornecedor_empresa_id' => $fornecedor->empresa_id,
                'empresa_ativa_id' => $empresa->id,
            ]);
            
            $this->clearFornecedorCache();

            return response()->json(['data' => new FornecedorResource($fornecedor)], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleUpdate para usar Repository DDD
     */
    protected function handleUpdate(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para editar fornecedores.',
            ], 403);
        }

        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            $data = array_merge($request->all(), $mergeParams);
            
            // Validar dados usando service (mantém validações existentes)
            $validator = $this->service->validateUpdateData($data, $id);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Erro de validação',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Buscar fornecedor existente via Repository DDD
            $fornecedorDomain = $this->fornecedorRepository->buscarPorId((int) $id);
            
            if (!$fornecedorDomain || $fornecedorDomain->empresaId !== $empresa->id) {
                return response()->json([
                    'message' => 'Fornecedor não encontrado ou não pertence à empresa ativa.'
                ], 404);
            }

            // Criar nova instância com dados atualizados
            $validated = $validator->validated();
            $fornecedorAtualizado = new \App\Domain\Fornecedor\Entities\Fornecedor(
                id: $fornecedorDomain->id,
                empresaId: $fornecedorDomain->empresaId,
                razaoSocial: $validated['razao_social'] ?? $fornecedorDomain->razaoSocial,
                cnpj: $validated['cnpj'] ?? $fornecedorDomain->cnpj,
                nomeFantasia: $validated['nome_fantasia'] ?? $fornecedorDomain->nomeFantasia,
                cep: $validated['cep'] ?? $fornecedorDomain->cep,
                logradouro: $validated['logradouro'] ?? $fornecedorDomain->logradouro,
                numero: $validated['numero'] ?? $fornecedorDomain->numero,
                bairro: $validated['bairro'] ?? $fornecedorDomain->bairro,
                complemento: $validated['complemento'] ?? $fornecedorDomain->complemento,
                cidade: $validated['cidade'] ?? $fornecedorDomain->cidade,
                estado: $validated['estado'] ?? $fornecedorDomain->estado,
                email: $validated['email'] ?? $fornecedorDomain->email,
                telefone: $validated['telefone'] ?? $fornecedorDomain->telefone,
                emails: $validated['emails'] ?? $fornecedorDomain->emails,
                telefones: $validated['telefones'] ?? $fornecedorDomain->telefones,
                contato: $validated['contato'] ?? $fornecedorDomain->contato,
                observacoes: $validated['observacoes'] ?? $fornecedorDomain->observacoes,
                isTransportadora: $validated['is_transportadora'] ?? $fornecedorDomain->isTransportadora,
            );

            // Atualizar via Repository DDD
            $fornecedorDomainAtualizado = $this->fornecedorRepository->atualizar($fornecedorAtualizado);
            
            // Buscar modelo Eloquent para Resource
            $fornecedor = Fornecedor::findOrFail($fornecedorDomainAtualizado->id);
            
            $this->clearFornecedorCache();

            return response()->json(['data' => new FornecedorResource($fornecedor)]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Sobrescrever handleDestroy para usar Repository DDD
     */
    protected function handleDestroy(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir fornecedores.',
            ], 403);
        }

        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            // Verificar se fornecedor existe e pertence à empresa
            $fornecedorDomain = $this->fornecedorRepository->buscarPorId((int) $id);
            
            if (!$fornecedorDomain || $fornecedorDomain->empresaId !== $empresa->id) {
                return response()->json([
                    'message' => 'Fornecedor não encontrado ou não pertence à empresa ativa.'
                ], 404);
            }

            // Deletar via Repository DDD
            $this->fornecedorRepository->deletar((int) $id);
            $this->clearFornecedorCache();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Métodos de compatibilidade
     */
    public function index(Request $request)
    {
        return $this->handleList($request);
    }

    public function list(Request $request)
    {
        return $this->handleList($request);
    }

    public function get(Request $request)
    {
        return $this->handleGet($request);
    }

    public function store(Request $request)
    {
        return $this->handleStore($request);
    }

    public function show(Fornecedor $fornecedor)
    {
        $request = request();
        $request->route()->setParameter('fornecedor', $fornecedor->id);
        return $this->handleGet($request);
    }

    public function update(Request $request, Fornecedor $fornecedor)
    {
        return $this->handleUpdate($request, $fornecedor->id);
    }

    public function destroy(Fornecedor $fornecedor)
    {
        return $this->handleDestroy(request(), $fornecedor->id);
    }

    /**
     * Limpar cache de fornecedores
     */
    protected function clearFornecedorCache(): void
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $tenantId = tenancy()->tenant?->id;
        
        \Log::debug('FornecedorController->clearFornecedorCache()', [
            'empresa_id' => $empresa->id,
            'tenant_id' => $tenantId,
        ]);
        
        if ($tenantId && RedisService::isAvailable()) {
            $pattern = "fornecedores:{$tenantId}:{$empresa->id}:*";
            try {
                $cursor = 0;
                $totalDeleted = 0;
                do {
                    $result = \Illuminate\Support\Facades\Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                    $cursor = $result[0];
                    $keys = $result[1];
                    if (!empty($keys)) {
                        $deleted = \Illuminate\Support\Facades\Redis::del($keys);
                        $totalDeleted += $deleted;
                    }
                } while ($cursor != 0);
                
                \Log::debug('FornecedorController->clearFornecedorCache() concluído', [
                    'pattern' => $pattern,
                    'total_deleted' => $totalDeleted,
                ]);
            } catch (\Exception $e) {
                \Log::warning('Erro ao limpar cache de fornecedores: ' . $e->getMessage());
            }
        }
    }
}

