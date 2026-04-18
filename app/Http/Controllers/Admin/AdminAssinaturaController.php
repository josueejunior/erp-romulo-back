<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Application\Assinatura\UseCases\ListarAssinaturasAdminUseCase;
use App\Application\Assinatura\UseCases\BuscarAssinaturaAdminUseCase;
use App\Application\Assinatura\UseCases\AtualizarAssinaturaAdminUseCase;
use App\Application\Assinatura\UseCases\CriarAssinaturaAdminUseCase;
use App\Application\Tenant\UseCases\ListarTenantsParaFiltroUseCase;
use App\Http\Requests\Assinatura\AtualizarAssinaturaAdminRequest;
use App\Http\Requests\Admin\TrocarPlanoAssinaturaAdminRequest;
use App\Http\Requests\Admin\StoreAssinaturaAdminRequest;
use App\Models\Tenant;
use App\Modules\Assinatura\Models\Assinatura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller Admin para gerenciar assinaturas de todos os tenants
 * 
 * 100% DDD - Apenas recebe request e delega para Use Cases
 * 
 * Padrão similar ao OrgaoController:
 * - Métodos auxiliares para extrair IDs da rota
 * - Métodos handle* que recebem IDs diretamente
 * - Métodos públicos que podem usar Route Model Binding ou extrair IDs
 */
class AdminAssinaturaController extends Controller
{
    public function __construct(
        private ListarAssinaturasAdminUseCase $listarAssinaturasAdminUseCase,
        private BuscarAssinaturaAdminUseCase $buscarAssinaturaAdminUseCase,
        private AtualizarAssinaturaAdminUseCase $atualizarAssinaturaAdminUseCase,
        private CriarAssinaturaAdminUseCase $criarAssinaturaAdminUseCase,
        private ListarTenantsParaFiltroUseCase $listarTenantsParaFiltroUseCase,
    ) {}

    /**
     * Extrai o tenant_id da rota
     * Padrão similar ao OrgaoController::getRouteId()
     */
    protected function getTenantIdFromRoute($route): ?int
    {
        $parameters = $route->parameters();
        // Tentar 'tenantId' primeiro, depois 'tenant', depois 'id'
        $id = $parameters['tenantId'] ?? $parameters['tenant'] ?? $parameters['id'] ?? null;
        return $id ? (int) $id : null;
    }

    /**
     * Extrai o assinatura_id da rota
     * Padrão similar ao OrgaoController::getRouteId()
     */
    protected function getAssinaturaIdFromRoute($route): ?int
    {
        $parameters = $route->parameters();
        // Tentar 'assinaturaId' primeiro, depois 'assinatura', depois 'id'
        $id = $parameters['assinaturaId'] ?? $parameters['assinatura'] ?? $parameters['id'] ?? null;
        return $id ? (int) $id : null;
    }

    /**
     * Listar todas as assinaturas de todos os tenants
     */
    public function index(Request $request)
    {
        try {
            $filtros = [
                'tenant_id' => $request->tenant_id,
                'status' => $request->status,
                'search' => $request->search,
            ];

            // Executar Use Case
            $todasAssinaturas = $this->listarAssinaturasAdminUseCase->executar($filtros);

            // Paginação manual
            $perPage = $request->per_page ?? 15;
            $currentPage = $request->page ?? 1;
            $total = $todasAssinaturas->count();
            $offset = ($currentPage - 1) * $perPage;
            $paginated = $todasAssinaturas->slice($offset, $perPage)->values();

            // Criar paginator manual (UseCase retorna Collection, não Paginator)
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $paginated,
                $total,
                $perPage,
                $currentPage,
                [
                    'path' => $request->url(),
                    'pageName' => 'page',
                ]
            );

            return ApiResponse::paginated($paginator);
        } catch (\Exception $e) {
            Log::error('Erro ao listar assinaturas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Erro ao listar assinaturas.', 500);
        }
    }

    /**
     * Método handleGet - recebe IDs diretamente
     * Padrão similar ao OrgaoController::handleGet()
     */
    protected function handleGet(Request $request, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        $route = $request->route();
        $tenantId = $this->getTenantIdFromRoute($route);
        $assinaturaId = $this->getAssinaturaIdFromRoute($route);
        
        if (!$tenantId || !$assinaturaId) {
            return ApiResponse::error('Tenant ID ou Assinatura ID não fornecido', 400);
        }

        try {
            // Executar Use Case
            $data = $this->buscarAssinaturaAdminUseCase->executar($tenantId, $assinaturaId);

            return ApiResponse::item($data);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar assinatura', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
            ]);
            return ApiResponse::error('Erro ao buscar assinatura.', 500);
        }
    }

    /**
     * Método público - pode usar Route Model Binding ou extrair IDs da rota
     * Padrão similar ao OrgaoController::show()
     * 
     * Suporta duas formas:
     * 1. Route Model Binding: show(Request $request, Tenant $tenant, Assinatura $assinatura)
     * 2. IDs numéricos: show(Request $request, int $tenantId, int $assinaturaId)
     */
    public function show(Request $request, Tenant|int $tenantOrId, Assinatura|int $assinaturaOrId = null)
    {
        // Se os modelos foram injetados via Route Model Binding, usar os IDs deles
        if ($tenantOrId instanceof Tenant && $assinaturaOrId instanceof Assinatura) {
            $request->route()->setParameter('tenantId', $tenantOrId->id);
            $request->route()->setParameter('assinaturaId', $assinaturaOrId->id);
        } elseif (is_int($tenantOrId) && is_int($assinaturaOrId)) {
            // Se são IDs numéricos, definir nos parâmetros da rota
            $request->route()->setParameter('tenantId', $tenantOrId);
            $request->route()->setParameter('assinaturaId', $assinaturaOrId);
        }
        
        return $this->handleGet($request);
    }

    /**
     * Método handleUpdate - recebe IDs diretamente
     * Padrão similar ao OrgaoController::handleUpdate()
     */
    protected function handleUpdate(AtualizarAssinaturaAdminRequest $request, int|string $tenantId, int|string $assinaturaId, array $mergeParams = []): \Illuminate\Http\JsonResponse
    {
        try {
            // Request já está validado via Form Request
            $validated = $request->validated();

            // Garantir que são inteiros
            $tenantId = (int) $tenantId;
            $assinaturaId = (int) $assinaturaId;

            // Executar Use Case
            $assinaturaDomain = $this->atualizarAssinaturaAdminUseCase->executar($tenantId, $assinaturaId, $validated);

            // Buscar modelo para resposta
            $assinaturaModel = $this->buscarAssinaturaAdminUseCase->executar($tenantId, $assinaturaId);

            return ApiResponse::success(
                'Assinatura atualizada com sucesso',
                $assinaturaModel
            );
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar assinatura', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Erro ao atualizar assinatura.', 500);
        }
    }

    /**
     * Método público - pode usar Route Model Binding ou extrair IDs da rota
     * Padrão similar ao OrgaoController::update()
     * 
     * Suporta duas formas:
     * 1. Route Model Binding: update(Request $request, Tenant $tenant, Assinatura $assinatura)
     * 2. IDs numéricos: update(Request $request, int $tenantId, int $assinaturaId)
     */
    public function update(AtualizarAssinaturaAdminRequest $request, Tenant|int $tenantOrId, Assinatura|int $assinaturaOrId = null)
    {
        // Se os modelos foram injetados via Route Model Binding, usar os IDs deles
        if ($tenantOrId instanceof Tenant && $assinaturaOrId instanceof Assinatura) {
            return $this->handleUpdate($request, $tenantOrId->id, $assinaturaOrId->id);
        } elseif (is_int($tenantOrId) && is_int($assinaturaOrId)) {
            // Se são IDs numéricos, usar diretamente
            return $this->handleUpdate($request, $tenantOrId, $assinaturaOrId);
        }
        
        // Caso contrário, extrair IDs da rota
        $route = $request->route();
        $tenantId = $this->getTenantIdFromRoute($route);
        $assinaturaId = $this->getAssinaturaIdFromRoute($route);
        
        if (!$tenantId || !$assinaturaId) {
            return ApiResponse::error('Tenant ID ou Assinatura ID não fornecido', 400);
        }
        
        return $this->handleUpdate($request, $tenantId, $assinaturaId);
    }

    /**
     * Criar nova assinatura para um tenant
     * 🔥 DDD: Controller fino - validação via FormRequest
     */
    public function store(StoreAssinaturaAdminRequest $request)
    {
        try {
            $validated = $request->validated();

            // Executar Use Case
            $assinatura = $this->criarAssinaturaAdminUseCase->executar(
                $validated['tenant_id'],
                $validated
            );

            // Buscar assinatura completa para resposta
            $assinaturaCompleta = $this->buscarAssinaturaAdminUseCase->executar(
                $validated['tenant_id'],
                $assinatura->id
            );

            return ApiResponse::success(
                'Assinatura criada com sucesso',
                $assinaturaCompleta,
                201
            );
        } catch (\App\Domain\Exceptions\DomainException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('Erro ao criar assinatura', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Erro ao criar assinatura.', 500);
        }
    }

    /**
     * Listar tenants para filtro
     */
    public function tenants()
    {
        try {
            // Executar Use Case
            $tenants = $this->listarTenantsParaFiltroUseCase->executar();

            return ApiResponse::collection($tenants->values()->all());
        } catch (\Exception $e) {
            Log::error('Erro ao listar tenants', ['error' => $e->getMessage()]);
            return ApiResponse::error('Erro ao listar empresas.', 500);
        }
    }

    /**
     * Trocar plano de uma assinatura (Admin)
     * 🔥 DDD: Controller fino - validação via FormRequest
     */
    public function trocarPlano(TrocarPlanoAssinaturaAdminRequest $request, int $tenantId, int $assinaturaId)
    {
        try {
            $validated = $request->validated();

            Log::debug('AdminAssinaturaController::trocarPlano - Iniciando', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'novo_plano_id' => $validated['novo_plano_id'] ?? null,
                'periodo' => $validated['periodo'] ?? null,
            ]);

            // Buscar assinatura atual
            $assinaturaAtual = $this->buscarAssinaturaAdminUseCase->executar($tenantId, $assinaturaId);
            if (!$assinaturaAtual) {
                Log::warning('AdminAssinaturaController::trocarPlano - Assinatura não encontrada', [
                    'tenant_id' => $tenantId,
                    'assinatura_id' => $assinaturaId,
                ]);
                return ApiResponse::error('Assinatura não encontrada.', 404);
            }

            $planoAtualId = is_array($assinaturaAtual) ? ($assinaturaAtual['plano_id'] ?? null) : ($assinaturaAtual->plano_id ?? null);
            $novoPlanoId = $validated['novo_plano_id'];

            Log::debug('AdminAssinaturaController::trocarPlano - Verificando plano atual', [
                'plano_atual_id' => $planoAtualId,
                'novo_plano_id' => $novoPlanoId,
                'sao_iguais' => $planoAtualId == $novoPlanoId,
            ]);

            // Se já está no mesmo plano, retornar erro com mensagem mais clara
            if ($planoAtualId == $novoPlanoId) {
                Log::info('AdminAssinaturaController::trocarPlano - Assinatura já está no mesmo plano', [
                    'tenant_id' => $tenantId,
                    'assinatura_id' => $assinaturaId,
                    'plano_id' => $novoPlanoId,
                ]);
                return ApiResponse::error('A assinatura já está no plano selecionado. Selecione um plano diferente para realizar a troca.', 400);
            }

            // Atualizar assinatura com novo plano
            Log::debug('AdminAssinaturaController::trocarPlano - Atualizando assinatura', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'novo_plano_id' => $novoPlanoId,
            ]);

            $this->atualizarAssinaturaAdminUseCase->executar(
                $tenantId,
                $assinaturaId,
                [
                    'plano_id' => $novoPlanoId,
                    'status' => 'ativa', // 🔥 Forçar ativação ao trocar de plano via Admin
                    // O valor_pago será atualizado automaticamente pelo Use Case
                ]
            );

            // Buscar assinatura completa atualizada
            $assinaturaCompleta = $this->buscarAssinaturaAdminUseCase->executar(
                $tenantId,
                $assinaturaId
            );

            Log::info('AdminAssinaturaController::trocarPlano - Plano alterado com sucesso', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'plano_anterior_id' => $planoAtualId,
                'novo_plano_id' => $novoPlanoId,
            ]);

            return ApiResponse::success(
                'Plano alterado com sucesso!',
                $assinaturaCompleta
            );
        } catch (\App\Domain\Exceptions\DomainException $e) {
            Log::error('AdminAssinaturaController::trocarPlano - DomainException', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error($e->getMessage(), 400);
        } catch (\App\Domain\Exceptions\NotFoundException $e) {
            Log::warning('AdminAssinaturaController::trocarPlano - NotFoundException', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Log::error('AdminAssinaturaController::trocarPlano - Exception', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Erro ao trocar plano.', 500);
        }
    }
}

