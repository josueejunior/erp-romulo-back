<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Application\Onboarding\UseCases\GerenciarOnboardingUseCase;
use App\Application\Onboarding\DTOs\IniciarOnboardingDTO;
use App\Application\Onboarding\DTOs\MarcarEtapaDTO;
use App\Application\Onboarding\DTOs\ConcluirOnboardingDTO;
use App\Application\Onboarding\DTOs\BuscarProgressoDTO;
use App\Application\Onboarding\Presenters\OnboardingApiPresenter;
use App\Domain\Onboarding\Repositories\OnboardingProgressRepositoryInterface;
use App\Http\Requests\Onboarding\MarcarEtapaRequest;
use App\Http\Requests\Onboarding\ConcluirOnboardingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Domain\Exceptions\DomainException;

/**
 * Controller para gerenciamento de onboarding (usuários autenticados)
 * 
 * ✅ DDD: Usa Form Requests para validação
 * ✅ DDD: Usa DTOs para entrada
 * ✅ DDD: Usa Use Cases para lógica de negócio
 * ✅ DDD: Usa Presenter para serialização
 * ✅ DDD: Não acessa Eloquent diretamente
 */
class OnboardingController extends BaseApiController
{
    use HasAuthContext;

    public function __construct(
        private readonly GerenciarOnboardingUseCase $gerenciarOnboardingUseCase,
        private readonly OnboardingProgressRepositoryInterface $repository,
        private readonly OnboardingApiPresenter $presenter,
    ) {}

    /**
     * Obtém status do onboarding do usuário autenticado
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado.',
            ], 401);
        }

        try {
            // Criar DTO usando dados do usuário autenticado
            $dto = BuscarProgressoDTO::fromRequest(
                requestData: [],
                tenantId: tenancy()->tenant?->id ?? null,
                userId: $user->id,
                email: $user->email,
            );

            // Buscar progresso
            $onboardingDomain = $this->gerenciarOnboardingUseCase->buscarProgresso($dto);

            if (!$onboardingDomain) {
                // Se não existe, criar um novo
                $iniciarDto = IniciarOnboardingDTO::fromRequest(
                    requestData: [],
                    tenantId: tenancy()->tenant?->id ?? null,
                    userId: $user->id,
                    email: $user->email,
                );
                $onboardingDomain = $this->gerenciarOnboardingUseCase->iniciar($iniciarDto);
            }

            // Buscar modelo para apresentação
            $onboardingModel = $this->repository->buscarModeloPorId($onboardingDomain->id);

            if (!$onboardingModel) {
                // Se não conseguir buscar modelo, usar dados da entidade
                return response()->json([
                    'success' => true,
                    'data' => $this->presenter->presentDomain($onboardingDomain),
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $this->presenter->present($onboardingModel),
            ]);
        } catch (\Exception $e) {
            Log::error('OnboardingController::status - Erro inesperado', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar status do onboarding.',
            ], 500);
        }
    }

    /**
     * Marca uma etapa como concluída
     */
    public function marcarEtapa(MarcarEtapaRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado.',
            ], 401);
        }

        // 🔥 CORREÇÃO: Inicializar variáveis antes do try para uso no catch
        $tenantId = null;
        $userId = null;
        $email = null;

        try {
            // 🔥 CORREÇÃO: Garantir que temos dados de identificação
            $tenantId = tenancy()->tenant?->id;
            $userId = $user->id;
            $email = $user->email;
            
            Log::info('OnboardingController::marcarEtapa - Dados de identificação', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'email' => $email,
                'request_data' => $request->validated(),
            ]);
            
            if (!$tenantId && !$userId && !$email) {
                Log::error('OnboardingController::marcarEtapa - Dados de identificação ausentes', [
                    'user' => $user,
                    'tenancy_initialized' => tenancy()->initialized ?? false,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível identificar o contexto do usuário.',
                ], 400);
            }
            
            // Criar DTO usando dados do usuário autenticado
            $dto = MarcarEtapaDTO::fromRequest(
                requestData: $request->validated(),
                tenantId: $tenantId,
                userId: $userId,
                email: $email,
            );

            // Executar Use Case
            $onboardingDomain = $this->gerenciarOnboardingUseCase->marcarEtapaConcluida($dto);

            // Buscar modelo para apresentação
            $onboardingModel = $this->repository->buscarModeloPorId($onboardingDomain->id);

            if (!$onboardingModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao recuperar dados do onboarding.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $this->presenter->present($onboardingModel),
            ]);
        } catch (\InvalidArgumentException $e) {
            // Capturar erro de validação do DTO
            Log::warning('OnboardingController::marcarEtapa - Dados de identificação inválidos', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'tenant_id' => $tenantId ?? null,
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('OnboardingController::marcarEtapa - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao marcar etapa.',
            ], 500);
        }
    }

    /**
     * Conclui o onboarding
     */
    public function concluir(ConcluirOnboardingRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado.',
            ], 401);
        }

        // 🔥 CORREÇÃO: Inicializar variáveis antes do try para uso no catch
        $tenantId = null;
        $userId = null;
        $email = null;

        try {
            // 🔥 CORREÇÃO: Garantir que temos dados de identificação
            $tenantId = tenancy()->tenant?->id;
            $userId = $user->id;
            $email = $user->email;
            
            Log::info('OnboardingController::concluir - Dados de identificação', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'email' => $email,
                'request_data' => $request->all(),
            ]);
            
            if (!$tenantId && !$userId && !$email) {
                Log::error('OnboardingController::concluir - Dados de identificação ausentes', [
                    'user' => $user,
                    'tenancy_initialized' => tenancy()->initialized ?? false,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Não foi possível identificar o contexto do usuário.',
                ], 400);
            }
            
            // Criar DTO usando dados do usuário autenticado
            $dto = ConcluirOnboardingDTO::fromRequest(
                requestData: $request->validated(),
                tenantId: $tenantId,
                userId: $userId,
                email: $email,
            );

            // Executar Use Case
            $onboardingDomain = $this->gerenciarOnboardingUseCase->concluir($dto);

            // Buscar modelo para apresentação
            $onboardingModel = $this->repository->buscarModeloPorId($onboardingDomain->id);

            if (!$onboardingModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao recuperar dados do onboarding.',
                ], 500);
            }

            Log::info('OnboardingController - Onboarding concluído', [
                'user_id' => $user->id,
                'tenant_id' => tenancy()->tenant?->id,
                'onboarding_id' => $onboardingDomain->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tutorial concluído com sucesso!',
                'data' => $this->presenter->present($onboardingModel),
            ]);
        } catch (\InvalidArgumentException $e) {
            // Capturar erro de validação do DTO
            Log::warning('OnboardingController::concluir - Dados de identificação inválidos', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
                'tenant_id' => $tenantId ?? null,
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('OnboardingController::concluir - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao concluir onboarding.',
            ], 500);
        }
    }
}
