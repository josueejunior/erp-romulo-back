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
     * Criar fornecedor usando Use Case DDD
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para cadastrar fornecedores.'], 403);
        }

        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $validated = $request->validate([
                'razao_social' => 'required|string|max:255',
                'cnpj' => 'nullable|string|max:18',
                'nome_fantasia' => 'nullable|string|max:255',
                'cep' => 'nullable|string|max:10',
                'logradouro' => 'nullable|string|max:255',
                'numero' => 'nullable|string|max:20',
                'bairro' => 'nullable|string|max:255',
                'complemento' => 'nullable|string|max:255',
                'cidade' => 'nullable|string|max:255',
                'estado' => 'nullable|string|max:2',
                'email' => 'nullable|email|max:255',
                'telefone' => 'nullable|string|max:20',
                'emails' => 'nullable|array',
                'telefones' => 'nullable|array',
                'contato' => 'nullable|string|max:255',
                'observacoes' => 'nullable|string',
                'is_transportadora' => 'nullable|boolean',
            ], [
                'razao_social.required' => 'A razão social é obrigatória.',
            ]);

            $validated['empresa_id'] = $empresa->id;
            
            $dto = CriarFornecedorDTO::fromArray($validated);
            $fornecedorDomain = $this->criarFornecedorUseCase->executar($dto);
            $fornecedor = Fornecedor::findOrFail($fornecedorDomain->id);
            
            $this->clearFornecedorCache();

            return response()->json(['data' => new FornecedorResource($fornecedor)], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Atualizar fornecedor usando Repository DDD
     */
    public function update(Request $request, Fornecedor $fornecedor): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para editar fornecedores.'], 403);
        }

        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $validated = $request->validate([
                'razao_social' => 'sometimes|required|string|max:255',
                'cnpj' => 'nullable|string|max:18',
                'nome_fantasia' => 'nullable|string|max:255',
                'cep' => 'nullable|string|max:10',
                'logradouro' => 'nullable|string|max:255',
                'numero' => 'nullable|string|max:20',
                'bairro' => 'nullable|string|max:255',
                'complemento' => 'nullable|string|max:255',
                'cidade' => 'nullable|string|max:255',
                'estado' => 'nullable|string|max:2',
                'email' => 'nullable|email|max:255',
                'telefone' => 'nullable|string|max:20',
                'emails' => 'nullable|array',
                'telefones' => 'nullable|array',
                'contato' => 'nullable|string|max:255',
                'observacoes' => 'nullable|string',
                'is_transportadora' => 'nullable|boolean',
            ]);

            $fornecedorDomain = $this->fornecedorRepository->buscarPorId($fornecedor->id);
            
            if (!$fornecedorDomain || $fornecedorDomain->empresaId !== $empresa->id) {
                return response()->json(['message' => 'Fornecedor não encontrado.'], 404);
            }

            $fornecedorAtualizado = new FornecedorDomain(
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

            $this->fornecedorRepository->atualizar($fornecedorAtualizado);
            $fornecedor->refresh();
            
            $this->clearFornecedorCache();

            return response()->json(['data' => new FornecedorResource($fornecedor)]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erro de validação', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Excluir fornecedor usando Repository DDD
     */
    public function destroy(Fornecedor $fornecedor): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json(['message' => 'Você não tem permissão para excluir fornecedores.'], 403);
        }

        try {
            $empresa = $this->getEmpresaAtivaOrFail();
            
            $fornecedorDomain = $this->fornecedorRepository->buscarPorId($fornecedor->id);
            
            if (!$fornecedorDomain || $fornecedorDomain->empresaId !== $empresa->id) {
                return response()->json(['message' => 'Fornecedor não encontrado.'], 404);
            }

            $this->fornecedorRepository->deletar($fornecedor->id);
            $this->clearFornecedorCache();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Métodos de compatibilidade para Route::module
     */
    public function list(Request $request): \Illuminate\Http\JsonResponse
    {
        return $this->index($request);
    }

    public function get(Request $request): \Illuminate\Http\JsonResponse
    {
        $route = $request->route();
        $id = $route->parameter('fornecedor') ?? $route->parameter('id');
        
        if (!$id) {
            return response()->json(['message' => 'ID não fornecido'], 400);
        }

        $fornecedor = Fornecedor::find($id);
        if (!$fornecedor) {
            return response()->json(['message' => 'Fornecedor não encontrado.'], 404);
        }

        return $this->show($fornecedor);
    }

    /**
     * Limpar cache de fornecedores
     */
    private function clearFornecedorCache(): void
    {
        $empresa = $this->getEmpresaAtivaOrFail();
        $tenantId = tenancy()->tenant?->id;
        
        if ($tenantId && RedisService::isAvailable()) {
            $pattern = "fornecedores:{$tenantId}:{$empresa->id}:*";
            try {
                $cursor = 0;
                do {
                    $result = \Illuminate\Support\Facades\Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                    $cursor = $result[0];
                    $keys = $result[1];
                    if (!empty($keys)) {
                        \Illuminate\Support\Facades\Redis::del($keys);
                    }
                } while ($cursor != 0);
            } catch (\Exception $e) {
                Log::warning('Erro ao limpar cache de fornecedores: ' . $e->getMessage());
            }
        }
    }
}

