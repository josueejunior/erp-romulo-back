<?php

namespace App\Modules\Orgao\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\SetorResource;
use App\Application\Setor\UseCases\CriarSetorUseCase;
use App\Application\Setor\UseCases\AtualizarSetorUseCase;
use App\Application\Setor\UseCases\ListarSetoresUseCase;
use App\Application\Setor\UseCases\BuscarSetorUseCase;
use App\Application\Setor\UseCases\DeletarSetorUseCase;
use App\Application\Setor\DTOs\CriarSetorDTO;
use App\Application\Setor\DTOs\AtualizarSetorDTO;
use App\Domain\Shared\ValueObjects\TenantContext;
use Illuminate\Http\Request;
use App\Helpers\PermissionHelper;
use App\Services\RedisService;
use App\Modules\Orgao\Models\Setor as SetorModel;

class SetorController extends BaseApiController
{
    public function __construct(
        private CriarSetorUseCase $criarSetorUseCase,
        private AtualizarSetorUseCase $atualizarSetorUseCase,
        private ListarSetoresUseCase $listarSetoresUseCase,
        private BuscarSetorUseCase $buscarSetorUseCase,
        private DeletarSetorUseCase $deletarSetorUseCase,
    ) {}

    /**
     * Sobrescrever handleList para usar SetorResource e cache
     */
    protected function handleList(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canView()) {
            return response()->json([
                'message' => 'Não autenticado.',
            ], 401);
        }

        $tenantId = tenancy()->tenant?->id;

        // Garantir que o TenantContext tenha empresa_id antes de chamar o use case
        try {
            if (!TenantContext::has() || TenantContext::get()->empresaId === null) {
                $empresa = $this->getEmpresaAtivaOrFail();
                if ($tenantId) {
                    TenantContext::set($tenantId, $empresa->id);
                }
            }
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao determinar empresa ativa');
        }
        
        // Criar chave de cache baseada nos filtros
        $filters = array_merge($request->all(), $mergeParams);
        $cacheKey = "setores:{$tenantId}:" . md5(json_encode($filters));
        
        // Tentar obter do cache
        if ($tenantId && RedisService::isAvailable()) {
            $cached = RedisService::get($cacheKey);
            if ($cached !== null) {
                return response()->json($cached);
            }
        }

        try {
            $setors = $this->listarSetoresUseCase->executar($filters);
            
            // Converter entidades para modelos para o Resource
            $setorIds = $setors->getCollection()->pluck('id')->toArray();
            $setorModels = SetorModel::whereIn('id', $setorIds)
                ->with('orgao')
                ->get()
                ->keyBy('id');
            
            // Manter a ordem e substituir entidades por modelos
            $setors->getCollection()->transform(function ($setor) use ($setorModels) {
                return $setorModels->get($setor->id);
            });
            
            $response = SetorResource::collection($setors);

            // Salvar no cache (5 minutos)
            if ($tenantId && RedisService::isAvailable()) {
                RedisService::set($cacheKey, $response->response()->getData(true), 300);
            }

            return response()->json($response);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao listar setores');
        }
    }

    /**
     * Extrair ID da rota
     */
    protected function getRouteId($route): ?int
    {
        $parameters = $route->parameters();
        // Tentar 'setor' primeiro (conforme Route::module), depois 'id'
        $id = $parameters['setor'] ?? $parameters['id'] ?? null;
        return $id ? (int) $id : null;
    }

    /**
     * Sobrescrever handleGet para usar SetorResource
     */
    protected function handleGet(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        $route = $request->route();
        $id = $this->getRouteId($route);
        
        if (!$id) {
            return response()->json(['message' => 'ID não fornecido'], 400);
        }

        try {
            $setor = $this->buscarSetorUseCase->executar($id);
            
            // Converter entidade para modelo para o Resource
            $setorModel = SetorModel::find($setor->id);
            if (!$setorModel) {
                return response()->json([
                    'message' => 'Setor não encontrado.'
                ], 404);
            }
            
            return response()->json(['data' => new SetorResource($setorModel)]);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao buscar setor');
        }
    }

    /**
     * Sobrescrever handleStore para validação de permissão e usar SetorResource
     */
    protected function handleStore(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para cadastrar setores.',
            ], 403);
        }

        // Garantir TenantContext com empresa_id antes de validar/crear
        try {
            $tenantId = tenancy()->tenant?->id;
            if (!TenantContext::has() || TenantContext::get()->empresaId === null) {
                $empresa = $this->getEmpresaAtivaOrFail();
                if ($tenantId) {
                    TenantContext::set($tenantId, $empresa->id);
                }
            }
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao determinar empresa ativa');
        }

        try {
            $validated = $request->validate([
                'orgao_id' => 'required|integer',
                'nome' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'telefone' => 'nullable|string|max:20',
                'observacoes' => 'nullable|string',
            ]);

            $data = array_merge($validated, $mergeParams);
            $dto = CriarSetorDTO::fromArray($data);
            $setor = $this->criarSetorUseCase->executar($dto);

            // Converter entidade para modelo para o Resource
            $setorModel = SetorModel::find($setor->id);
            if ($setorModel) {
                $setorModel->load('orgao');
            }

            // Limpar cache
            $this->clearSetorCache();

            return response()->json(['data' => new SetorResource($setorModel)], 201);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao criar setor');
        }
    }

    /**
     * Sobrescrever handleUpdate para validação de permissão e usar SetorResource
     */
    protected function handleUpdate(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para editar setores.',
            ], 403);
        }

        try {
            $validated = $request->validate([
                'nome' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'telefone' => 'nullable|string|max:20',
                'observacoes' => 'nullable|string',
            ]);

            $data = array_merge($validated, $mergeParams);
            $dto = AtualizarSetorDTO::fromArray($data);
            $setor = $this->atualizarSetorUseCase->executar((int) $id, $dto);

            // Converter entidade para modelo para o Resource
            $setorModel = SetorModel::find($setor->id);
            if ($setorModel) {
                $setorModel->load('orgao');
            }

            // Limpar cache
            $this->clearSetorCache();

            return response()->json(['data' => new SetorResource($setorModel)]);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao atualizar setor');
        }
    }

    /**
     * Sobrescrever handleDestroy para validação de permissão
     */
    protected function handleDestroy(Request $request, int|string $id, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        if (!PermissionHelper::canManageMasterData()) {
            return response()->json([
                'message' => 'Você não tem permissão para excluir setores.',
            ], 403);
        }

        try {
            $this->deletarSetorUseCase->executar((int) $id);
            
            // Limpar cache
            $this->clearSetorCache();

            return response()->json(null, 204);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Erro ao deletar setor');
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

    public function store(Request $request)
    {
        return $this->handleStore($request);
    }

    public function get(Request $request, int|string $id)
    {
        return $this->handleGet($request);
    }

    public function show(Request $request, int|string $id)
    {
        return $this->handleGet($request);
    }

    public function update(Request $request, int|string $id)
    {
        return $this->handleUpdate($request, $id);
    }

    public function destroy(Request $request, int|string $id)
    {
        return $this->handleDestroy($request, $id);
    }

    /**
     * Limpar cache de setores
     */
    protected function clearSetorCache(): void
    {
        $tenantId = tenancy()->tenant?->id;
        
        if ($tenantId && RedisService::isAvailable()) {
            $pattern = "setores:{$tenantId}:*";
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
                \Log::warning('Erro ao limpar cache de setores: ' . $e->getMessage());
            }
        }
    }
}


