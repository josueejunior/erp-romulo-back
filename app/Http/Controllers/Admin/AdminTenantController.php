<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Application\Tenant\UseCases\CriarTenantUseCase;
use App\Application\Tenant\UseCases\ListarTenantsAdminUseCase;
use App\Application\Tenant\UseCases\BuscarTenantAdminUseCase;
use App\Application\Tenant\UseCases\AtualizarTenantAdminUseCase;
use App\Application\Tenant\UseCases\InativarTenantAdminUseCase;
use App\Application\Tenant\UseCases\ReativarTenantAdminUseCase;
use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Domain\Tenant\Events\EmpresaCriada;
use App\Domain\Shared\Events\EventDispatcherInterface;
use App\Http\Responses\ApiResponse;
use App\Http\Requests\Admin\StoreTenantAdminRequest;
use App\Http\Requests\Admin\UpdateTenantAdminRequest;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Domain\Exceptions\DomainException;

/**
 * 🔥 DDD: Controller Admin para gerenciar empresas (tenants)
 * 
 * Controller FINO - apenas recebe request e devolve response
 * Toda lógica está nos UseCases, Domain Services e FormRequests
 * 
 * Responsabilidades:
 * - Receber request HTTP
 * - Validar entrada (via FormRequest)
 * - Chamar UseCase apropriado
 * - Retornar response padronizado (ApiResponse)
 */
class AdminTenantController extends Controller
{
    public function __construct(
        private CriarTenantUseCase $criarTenantUseCase,
        private ListarTenantsAdminUseCase $listarTenantsAdminUseCase,
        private BuscarTenantAdminUseCase $buscarTenantAdminUseCase,
        private AtualizarTenantAdminUseCase $atualizarTenantAdminUseCase,
        private InativarTenantAdminUseCase $inativarTenantAdminUseCase,
        private ReativarTenantAdminUseCase $reativarTenantAdminUseCase,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Listar todas as empresas (tenants)
     * 🔥 DDD: Controller fino - delega para UseCase
     */
    public function index(Request $request)
    {
        try {
            // Preparar filtros
            $filters = [
                'status' => $request->status,
                'per_page' => $request->per_page ?? 15,
            ];

            // Se houver busca, usar campo search genérico
            if ($request->search || $request->razao_social || $request->cnpj || $request->email) {
                $filters['search'] = $request->search 
                    ?? $request->razao_social 
                    ?? $request->cnpj 
                    ?? $request->email;
            }

            // 🔥 DDD: Delegar para UseCase
            $tenants = $this->listarTenantsAdminUseCase->executar($filters);

            // Retornar response padronizado
            return ApiResponse::paginated($tenants);
        } catch (\Exception $e) {
            Log::error('Erro ao listar empresas', ['error' => $e->getMessage()]);
            return ApiResponse::error('Erro ao listar empresas.', 500);
        }
    }

    /**
     * Buscar empresa específica
     * 🔥 DDD: Controller fino - delega para UseCase
     */
    public function show(Tenant $tenant)
    {
        try {
            $tenantData = $this->buscarTenantAdminUseCase->executar($tenant->id);
            return ApiResponse::item($tenantData);
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar empresa', ['error' => $e->getMessage()]);
            return ApiResponse::error('Erro ao buscar empresa.', 500);
        }
    }

    /**
     * Criar nova empresa com banco de dados separado
     * 🔥 DDD: Controller fino - validação via FormRequest, delega para UseCase
     */
    public function store(StoreTenantAdminRequest $request)
    {
        try {
            // Criar DTO (pode incluir dados de admin se fornecidos)
            $validated = $request->validated();
            $dto = CriarTenantDTO::fromArray($validated);

            // Executar Use Case - cria tenant com status 'pending' e dispara Job
            $result = $this->criarTenantUseCase->executar($dto, requireAdmin: false);

            // ⚠️ NÃO disparar evento de EmpresaCriada aqui - empresa será criada pelo Job
            // O evento será disparado no Job após a criação completa

            Log::info('AdminTenantController::store - Tenant criado, processamento iniciado em background', [
                'tenant_id' => $result['tenant']->id,
                'status' => $result['status'],
            ]);

            return ApiResponse::success(
                'Empresa criada. Processamento em andamento...',
                [
                    'tenant' => [
                        'id' => $result['tenant']->id,
                        'razao_social' => $result['tenant']->razaoSocial,
                        'cnpj' => $result['tenant']->cnpj,
                        'email' => $result['tenant']->email,
                        'status' => $result['tenant']->status,
                    ],
                    'message' => $result['message'],
                ],
                202 // 202 Accepted - indica que a requisição foi aceita mas processamento está em andamento
            );

        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('Erro ao criar empresa', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Erro ao criar empresa. Tente novamente.',
                500,
                config('app.debug') ? $e->getMessage() : null
            );
        }
    }

    /**
     * Atualizar empresa
     * 🔥 DDD: Controller fino - validação via FormRequest, delega para UseCase
     */
    public function update(UpdateTenantAdminRequest $request, Tenant $tenant)
    {
        try {
            $tenantAtualizado = $this->atualizarTenantAdminUseCase->executar(
                $tenant->id,
                $request->validated()
            );

            return ApiResponse::success(
                'Empresa atualizada com sucesso!',
                [
                    'id' => $tenantAtualizado->id,
                    'razao_social' => $tenantAtualizado->razaoSocial,
                    'cnpj' => $tenantAtualizado->cnpj,
                    'status' => $tenantAtualizado->status,
                ]
            );

        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar empresa', ['error' => $e->getMessage()]);
            return ApiResponse::error('Erro ao atualizar empresa.', 500);
        }
    }

    /**
     * Inativar empresa (soft delete)
     * 🔥 DDD: Controller fino - delega para UseCase
     */
    public function destroy(Tenant $tenant)
    {
        try {
            $this->inativarTenantAdminUseCase->executar($tenant->id);
            return ApiResponse::success('Empresa inativada com sucesso!');
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Log::error('Erro ao inativar empresa', ['error' => $e->getMessage()]);
            return ApiResponse::error('Erro ao inativar empresa.', 500);
        }
    }

    /**
     * Reativar empresa
     * 🔥 DDD: Controller fino - delega para UseCase
     */
    public function reactivate(Tenant $tenant)
    {
        try {
            $this->reativarTenantAdminUseCase->executar($tenant->id);
            return ApiResponse::success('Empresa reativada com sucesso!');
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Log::error('Erro ao reativar empresa', ['error' => $e->getMessage()]);
            return ApiResponse::error('Erro ao reativar empresa.', 500);
        }
    }
}

