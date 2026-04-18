<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasAuthContext;
use App\Application\Onboarding\UseCases\GerenciarOnboardingUseCase;
use App\Application\Onboarding\DTOs\IniciarOnboardingDTO;
use App\Application\Onboarding\DTOs\MarcarEtapaDTO;
use App\Application\Onboarding\DTOs\MarcarChecklistItemDTO;
use App\Application\Onboarding\DTOs\ConcluirOnboardingDTO;
use App\Application\Onboarding\DTOs\BuscarProgressoDTO;
use App\Application\Onboarding\Presenters\OnboardingApiPresenter;
use App\Domain\Onboarding\Repositories\OnboardingProgressRepositoryInterface;
use App\Http\Requests\Onboarding\IniciarOnboardingRequest;
use App\Http\Requests\Onboarding\MarcarEtapaRequest;
use App\Http\Requests\Onboarding\MarcarChecklistItemRequest;
use App\Http\Requests\Onboarding\ConcluirOnboardingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Domain\Exceptions\DomainException;

/**
 * Controller para gerenciamento de onboarding (rotas públicas)
 * 
 * ✅ DDD: Usa Form Requests para validação
 * ✅ DDD: Usa DTOs para entrada
 * ✅ DDD: Usa Use Cases para lógica de negócio
 * ✅ DDD: Usa Presenter para serialização
 * ✅ DDD: Não acessa Eloquent diretamente
 * 
 * ✅ Padrão: Usa trait HasAuthContext para obter contexto (como OrgaoController)
 * O middleware já inicializou o contexto (ApplicationContext), então apenas usamos os métodos do trait
 */
class OnboardingController extends Controller
{
    use HasAuthContext;

    public function __construct(
        private readonly GerenciarOnboardingUseCase $gerenciarOnboardingUseCase,
        private readonly OnboardingProgressRepositoryInterface $repository,
        private readonly OnboardingApiPresenter $presenter,
    ) {}

    /**
     * Inicia ou retoma onboarding
     */
    public function iniciar(IniciarOnboardingRequest $request): JsonResponse
    {
        try {
            // Obter contexto do trait (já inicializado pelo middleware)
            $tenantId = $this->getTenantId();
            $userId = $this->getUserId();
            $user = $this->getUser();
            $email = $user?->email;
            
            // Converter tenantId de string para int se necessário
            $tenantIdInt = $tenantId ? (int) $tenantId : null;
            
            // Criar DTO com dados do request e contexto
            $dto = IniciarOnboardingDTO::fromRequest(
                requestData: $request->validated(),
                tenantId: $tenantIdInt,
                userId: $userId,
                email: $email,
            );

            // Executar Use Case
            $onboardingDomain = $this->gerenciarOnboardingUseCase->iniciar($dto);

            // Buscar modelo para apresentação (se necessário)
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
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('OnboardingController::iniciar - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao iniciar onboarding.',
            ], 500);
        }
    }

    /**
     * Marca uma etapa como concluída
     */
    public function marcarEtapa(MarcarEtapaRequest $request): JsonResponse
    {
        try {
            // Obter contexto do trait (já inicializado pelo middleware)
            $tenantId = $this->getTenantId();
            $userId = $this->getUserId();
            $user = $this->getUser();
            $email = $user?->email;
            
            // Converter tenantId de string para int se necessário
            $tenantIdInt = $tenantId ? (int) $tenantId : null;
            
            // Criar DTO com dados do request e contexto
            $dto = MarcarEtapaDTO::fromRequest(
                requestData: $request->validated(),
                tenantId: $tenantIdInt,
                userId: $userId,
                email: $email,
            );

            // Executar Use Case (já calcula próxima etapa internamente)
            $onboardingDomain = $this->gerenciarOnboardingUseCase->marcarEtapaConcluida($dto);

            // Buscar modelo para apresentação
            $onboardingModel = $this->repository->buscarModeloPorId($onboardingDomain->id);

            if (!$onboardingModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao recuperar dados do onboarding.',
                ], 500);
            }

            // 🔥 MELHORIA: Presenter já inclui next_recommended_step
            $responseData = $this->presenter->present($onboardingModel);

            return response()->json([
                'success' => true,
                'data' => $responseData,
                // 🔥 MELHORIA: Incluir próxima etapa recomendada explicitamente na resposta
                'next_recommended_step' => $responseData['next_recommended_step'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            // Capturar erro de validação do DTO
            Log::warning('OnboardingController::marcarEtapa (Public) - Dados de identificação inválidos', [
                'error' => $e->getMessage(),
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
            Log::error('OnboardingController::marcarEtapa (Public) - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao marcar etapa.',
            ], 500);
        }
    }

    /**
     * Marca item do checklist como concluído
     */
    public function marcarChecklistItem(MarcarChecklistItemRequest $request): JsonResponse
    {
        try {
            // Obter contexto do trait (já inicializado pelo middleware)
            $tenantId = $this->getTenantId();
            $userId = $this->getUserId();
            $user = $this->getUser();
            $email = $user?->email;
            
            // Converter tenantId de string para int se necessário
            $tenantIdInt = $tenantId ? (int) $tenantId : null;
            
            // Criar DTO com dados do request e contexto
            $dto = MarcarChecklistItemDTO::fromRequest(
                requestData: $request->validated(),
                tenantId: $tenantIdInt,
                userId: $userId,
                email: $email,
            );

            // Executar Use Case
            $onboardingDomain = $this->gerenciarOnboardingUseCase->marcarChecklistItem($dto);

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
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('OnboardingController::marcarChecklistItem - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao marcar item do checklist.',
            ], 500);
        }
    }

    /**
     * Conclui o onboarding
     */
    public function concluir(ConcluirOnboardingRequest $request): JsonResponse
    {
        try {
            Log::info('OnboardingController::concluir (Public) - INÍCIO', [
                'request_data' => $request->validated(),
                'request_all' => $request->all(),
            ]);
            
            // Obter contexto do trait (já inicializado pelo middleware)
            $tenantId = $this->getTenantId();
            $userId = $this->getUserId();
            $user = $this->getUser();
            $email = $user?->email;
            
            Log::info('OnboardingController::concluir (Public) - Dados do contexto', [
                'tenantId' => $tenantId,
                'tenantId_type' => gettype($tenantId),
                'userId' => $userId,
                'userId_type' => gettype($userId),
                'email' => $email,
                'user_exists' => $user !== null,
            ]);
            
            // Converter tenantId de string para int se necessário
            $tenantIdInt = $tenantId ? (int) $tenantId : null;
            
            Log::info('OnboardingController::concluir (Public) - Criando DTO', [
                'tenantIdInt' => $tenantIdInt,
                'userId' => $userId,
                'email' => $email,
                'request_validated' => $request->validated(),
            ]);
            
            // Criar DTO com dados do request e contexto
            $dto = ConcluirOnboardingDTO::fromRequest(
                requestData: $request->validated(),
                tenantId: $tenantIdInt,
                userId: $userId,
                email: $email,
            );
            
            Log::info('OnboardingController::concluir (Public) - DTO criado com sucesso', [
                'dto_tenantId' => $dto->tenantId,
                'dto_userId' => $dto->userId,
                'dto_email' => $dto->email,
            ]);

            // Executar Use Case
            Log::info('OnboardingController::concluir (Public) - Executando UseCase');
            $onboardingDomain = $this->gerenciarOnboardingUseCase->concluir($dto);
            Log::info('OnboardingController::concluir (Public) - UseCase executado', [
                'onboarding_id' => $onboardingDomain->id,
            ]);

            // Buscar modelo para apresentação
            $onboardingModel = $this->repository->buscarModeloPorId($onboardingDomain->id);

            if (!$onboardingModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao recuperar dados do onboarding.',
                ], 500);
            }

            Log::info('OnboardingController::concluir - Onboarding concluído', [
                'onboarding_id' => $onboardingDomain->id,
                'user_id' => $userId,
                'tenant_id' => $tenantIdInt,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding concluído com sucesso!',
                'data' => $this->presenter->present($onboardingModel),
            ]);
        } catch (\InvalidArgumentException $e) {
            // Capturar erro de validação do DTO
            Log::warning('OnboardingController::concluir (Public) - Dados de identificação inválidos', [
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'tenantId' => $tenantId ?? null,
                'userId' => $userId ?? null,
                'email' => $email ?? null,
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (DomainException $e) {
            Log::warning('OnboardingController::concluir (Public) - DomainException', [
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'tenantId' => $tenantId ?? null,
                'userId' => $userId ?? null,
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('OnboardingController::concluir (Public) - Erro inesperado', [
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'tenantId' => $tenantId ?? null,
                'userId' => $userId ?? null,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao concluir onboarding.',
            ], 500);
        }
    }

    /**
     * Verifica se onboarding está concluído
     * Retorna status completo do onboarding
     */
    public function verificarStatus(Request $request): JsonResponse
    {
        try {
            // Obter contexto do trait (já inicializado pelo middleware)
            $tenantId = $this->getTenantId();
            $userId = $this->getUserId();
            $user = $this->getUser();
            $email = $user?->email;
            
            // Converter tenantId de string para int se necessário
            $tenantIdInt = $tenantId ? (int) $tenantId : null;
            
            // Criar DTO
            $dto = BuscarProgressoDTO::fromRequest(
                requestData: $request->all(),
                tenantId: $tenantIdInt,
                userId: $userId,
                email: $email,
            );

            // Buscar progresso
            $onboardingDomain = $this->gerenciarOnboardingUseCase->buscarProgresso($dto);

            if (!$onboardingDomain) {
                // Se não existe, criar novo
                $iniciarDto = IniciarOnboardingDTO::fromRequest(
                    requestData: $request->all(),
                    tenantId: $tenantIdInt,
                    userId: $userId,
                    email: $email,
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
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('OnboardingController::verificarStatus - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao verificar status do onboarding.',
            ], 500);
        }
    }

    /**
     * Busca progresso atual
     */
    public function buscarProgresso(Request $request): JsonResponse
    {
        try {
            // Obter contexto do trait (já inicializado pelo middleware)
            $tenantId = $this->getTenantId();
            $userId = $this->getUserId();
            $user = $this->getUser();
            $email = $user?->email;
            
            // Converter tenantId de string para int se necessário
            $tenantIdInt = $tenantId ? (int) $tenantId : null;
            
            // Criar DTO
            $dto = BuscarProgressoDTO::fromRequest(
                requestData: $request->all(),
                tenantId: $tenantIdInt,
                userId: $userId,
                email: $email,
            );

            // Buscar progresso
            $onboardingDomain = $this->gerenciarOnboardingUseCase->buscarProgresso($dto);

            if (!$onboardingDomain) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum progresso de onboarding encontrado.',
                ], 404);
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
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            Log::error('OnboardingController::buscarProgresso - Erro inesperado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar progresso do onboarding.',
            ], 500);
        }
    }
}
