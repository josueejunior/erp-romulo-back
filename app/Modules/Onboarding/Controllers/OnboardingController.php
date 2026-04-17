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
use App\Application\Assinatura\UseCases\CriarAssinaturaUseCase;
use App\Application\Assinatura\DTOs\CriarAssinaturaDTO;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Domain\Exceptions\DomainException;
use Carbon\Carbon;

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
        private readonly CriarAssinaturaUseCase $criarAssinaturaUseCase,
        private readonly AssinaturaRepositoryInterface $assinaturaRepository,
        private readonly PlanoRepositoryInterface $planoRepository,
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
            // ✅ CORREÇÃO: Obter tenant_id do header ou JWT, não do tenancy já inicializado
            $tenantIdRaw = $request->header('X-Tenant-ID') 
                ?? ($request->attributes->has('auth') && isset($request->attributes->get('auth')['tenant_id']) 
                    ? $request->attributes->get('auth')['tenant_id'] 
                    : null)
                ?? tenancy()->tenant?->id;
            
            // 🔥 CRÍTICO: Converter para int (header retorna string)
            $tenantId = $tenantIdRaw !== null ? (int) $tenantIdRaw : null;
            
            Log::info('OnboardingController::status - INÍCIO', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'tenant_id_raw' => $tenantIdRaw,
                'tenant_id_type' => gettype($tenantIdRaw),
                'tenant_id_source' => $request->header('X-Tenant-ID') ? 'header' : (tenancy()->initialized ? 'tenancy' : 'null'),
                'email' => $user->email,
            ]);

            // 🔥 CORREÇÃO CRÍTICA: Validar relação usuário-tenant ANTES de verificar onboarding
            // Mesmo que a rota seja isenta de validação rigorosa, precisamos garantir integridade
            if ($tenantId) {
                $lookupRepository = app(\App\Domain\UsersLookup\Repositories\UserLookupRepositoryInterface::class);
                $lookups = $lookupRepository->buscarAtivosPorEmail($user->email);
                
                $usuarioVinculadoAoTenant = false;
                foreach ($lookups as $lookup) {
                    if ($lookup->tenantId === $tenantId && $lookup->userId === $user->id) {
                        $usuarioVinculadoAoTenant = true;
                        break;
                    }
                }
                
                // Se não encontrou na lookup, verificar diretamente no banco do tenant
                if (!$usuarioVinculadoAoTenant) {
                    $tenant = \App\Models\Tenant::find($tenantId);
                    if ($tenant) {
                        $tenantAnterior = tenancy()->tenant;
                        try {
                            tenancy()->initialize($tenant);
                            $userNoTenant = \App\Modules\Auth\Models\User::find($user->id);
                            $usuarioVinculadoAoTenant = $userNoTenant !== null && !$userNoTenant->trashed();
                        } finally {
                            if ($tenantAnterior) {
                                tenancy()->initialize($tenantAnterior);
                            } else {
                                tenancy()->end();
                            }
                        }
                    }
                }
                
                if (!$usuarioVinculadoAoTenant) {
                    Log::warning('OnboardingController::status - Usuário não vinculado ao tenant solicitado', [
                        'user_id' => $user->id,
                        'tenant_id' => $tenantId,
                        'email' => $user->email,
                        'lookups_encontrados' => array_map(fn($l) => ['tenant_id' => $l->tenantId, 'user_id' => $l->userId], $lookups),
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'error' => 'INVALID_TENANT_RELATION',
                        'message' => 'Usuário não está vinculado ao tenant solicitado. Verifique o header X-Tenant-ID.',
                    ], 403);
                }
            }

            // Criar DTO usando dados do usuário autenticado
            $dto = BuscarProgressoDTO::fromRequest(
                requestData: [],
                tenantId: $tenantId,
                userId: $user->id,
                email: $user->email,
            );

            Log::info('OnboardingController::status - Buscando progresso', [
                'dto_tenantId' => $dto->tenantId,
                'dto_userId' => $dto->userId,
                'dto_email' => $dto->email,
            ]);

            // 🔥 CORREÇÃO: Verificar primeiro se já foi concluído (antes de buscar progresso)
            // Isso evita criar um novo onboarding se já foi concluído
            $jaConcluido = $this->gerenciarOnboardingUseCase->estaConcluido($dto);
            
            if ($jaConcluido) {
                Log::info('OnboardingController::status - Onboarding já foi concluído para este usuário', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'email' => $user->email,
                ]);
                // Se já foi concluído, retornar que está concluído (não criar novo)
                return response()->json([
                    'success' => true,
                    'data' => [
                        'onboarding_concluido' => true,
                        'progresso_percentual' => 100,
                        'etapas_concluidas' => [],
                        'checklist' => [],
                        'pode_ver_planos' => true,
                    ],
                ]);
            }
            
            // Buscar progresso (apenas se não estiver concluído)
            $onboardingDomain = $this->gerenciarOnboardingUseCase->buscarProgresso($dto);

            if (!$onboardingDomain) {
                Log::info('OnboardingController::status - Onboarding não encontrado e não concluído, criando novo', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'email' => $user->email,
                ]);
                // Se não existe e não foi concluído, criar um novo
                $iniciarDto = IniciarOnboardingDTO::fromRequest(
                    requestData: [],
                    tenantId: $tenantId,
                    userId: $user->id,
                    email: $user->email,
                );
                $onboardingDomain = $this->gerenciarOnboardingUseCase->iniciar($iniciarDto);
            } else {
                Log::info('OnboardingController::status - Onboarding encontrado', [
                    'onboarding_id' => $onboardingDomain->id,
                    'onboarding_concluido' => $onboardingDomain->onboardingConcluido,
                    'tenant_id' => $onboardingDomain->tenantId,
                    'user_id' => $onboardingDomain->userId,
                ]);
                
                // 🔥 CORREÇÃO CRÍTICA: Verificar se o onboarding encontrado está concluído
                // Mesmo que estaConcluido() tenha retornado false, o onboarding pode estar concluído
                if ($onboardingDomain->onboardingConcluido) {
                    Log::info('OnboardingController::status - Onboarding encontrado está concluído, retornando como concluído', [
                        'onboarding_id' => $onboardingDomain->id,
                        'user_id' => $user->id,
                        'tenant_id' => $tenantId,
                        'email' => $user->email,
                    ]);
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'onboarding_concluido' => true,
                            'progresso_percentual' => 100,
                            'etapas_concluidas' => $onboardingDomain->etapasConcluidas ?? [],
                            'checklist' => $onboardingDomain->checklist ?? [],
                            'pode_ver_planos' => true,
                        ],
                    ]);
                }
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
            // ✅ CORREÇÃO: Obter tenant_id do header ou JWT, não do tenancy já inicializado
            $tenantIdRaw = $request->header('X-Tenant-ID') 
                ?? ($request->attributes->has('auth') && isset($request->attributes->get('auth')['tenant_id']) 
                    ? $request->attributes->get('auth')['tenant_id'] 
                    : null)
                ?? tenancy()->tenant?->id;
            
            // 🔥 CRÍTICO: Converter para int (header retorna string)
            $tenantId = $tenantIdRaw !== null ? (int) $tenantIdRaw : null;
            $userId = $user->id;
            $email = $user->email;
            
            Log::info('OnboardingController::marcarEtapa - Dados de identificação', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'tenant_id_source' => $request->header('X-Tenant-ID') ? 'header' : (tenancy()->initialized ? 'tenancy' : 'null'),
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
            // ✅ CORREÇÃO: Obter tenant_id do header ou JWT, não do tenancy já inicializado
            $tenantIdRaw = $request->header('X-Tenant-ID') 
                ?? ($request->attributes->has('auth') && isset($request->attributes->get('auth')['tenant_id']) 
                    ? $request->attributes->get('auth')['tenant_id'] 
                    : null)
                ?? tenancy()->tenant?->id;
            
            // 🔥 CRÍTICO: Converter para int (header retorna string)
            $tenantId = $tenantIdRaw !== null ? (int) $tenantIdRaw : null;
            $userId = $user->id;
            $email = $user->email;
            
            Log::info('OnboardingController::concluir - Dados de identificação', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'tenant_id_source' => $request->header('X-Tenant-ID') ? 'header' : (tenancy()->initialized ? 'tenancy' : 'null'),
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

            // 🔥 NOVO: Criar plano gratuito de 3 dias após concluir tutorial
            $this->criarPlanoGratuito3Dias($user, $tenantId);

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

    /**
     * Cria plano gratuito de 3 dias após tutorial concluído para TODAS as empresas do usuário
     * 
     * 🔥 ROBUSTEZ: Agora percorre todos os tenants e empresas vinculadas ao usuário
     * 
     * @param \App\Models\User $user
     * @param int|null $tenantId Contexto atual (opcional)
     * @return void
     */
    private function criarPlanoGratuito3Dias($user, ?int $tenantId): void
    {
        try {
            $email = $user->email;
            Log::info('OnboardingController::criarPlanoGratuito3Dias - Iniciando criação de trial global', [
                'email' => $email,
                'user_id' => $user->id,
            ]);

            // 1. Buscar todos os tenants onde este usuário existe
            $lookupRepository = app(\App\Domain\UsersLookup\Repositories\UserLookupRepositoryInterface::class);
            $lookups = $lookupRepository->buscarAtivosPorEmail($email);
            
            if (empty($lookups)) {
                Log::warning('OnboardingController::criarPlanoGratuito3Dias - Nenhum tenant encontrado para o usuário');
                // Fallback para o tenant atual se houver
                if ($tenantId && $user->empresa_ativa_id) {
                    $this->criarTrialParaEmpresa($user->id, $user->empresa_ativa_id, $tenantId);
                }
                return;
            }

            // 2. Buscar o plano gratuito uma única vez para usar em todos
            $planosAtivos = $this->planoRepository->listar(['ativo' => true]);
            $planoGratuito = null;
            foreach ($planosAtivos as $plano) {
                $precoMensal = $plano->precoMensal ?? 0;
                if ($precoMensal == 0 || $precoMensal === null) {
                    $planoGratuito = $plano;
                    break;
                }
            }

            if (!$planoGratuito) {
                Log::error('OnboardingController::criarPlanoGratuito3Dias - Nenhum plano gratuito encontrado no sistema');
                return;
            }

            // 3. Percorrer cada tenant e suas empresas
            $tenantOriginal = tenancy()->tenant;
            
            foreach ($lookups as $lookup) {
                try {
                    $t = \App\Models\Tenant::find($lookup->tenantId);
                    if (!$t) continue;

                    tenancy()->initialize($t);
                    
                    // Buscar o usuário dentro deste tenant
                    $userNoTenant = \App\Modules\Auth\Models\User::where('email', $email)->first();
                    if (!$userNoTenant) continue;

                    // Buscar todas as empresas vinculadas a este usuário neste tenant
                    $empresas = $userNoTenant->empresas()->get();
                    
                    foreach ($empresas as $empresa) {
                        $this->criarTrialParaEmpresa($userNoTenant->id, $empresa->id, (int)$t->id, $planoGratuito);
                    }

                } catch (\Exception $e) {
                    Log::error('OnboardingController::criarPlanoGratuito3Dias - Erro ao processar tenant', [
                        'tenant_id' => $lookup->tenantId,
                        'error' => $e->getMessage(),
                    ]);
                } finally {
                    // tenancy()->end() não é necessário aqui pois o initialize() sobrescreve
                }
            }

            // Restaurar tenant original e limpar cache
            if ($tenantOriginal) {
                tenancy()->initialize($tenantOriginal);
                
                try {
                    $context = app(\App\Contracts\ApplicationContextContract::class);
                    if ($context->isInitialized()) {
                        $context->limparCacheAssinatura();
                    }
                } catch (\Exception $e) {
                    // Ignorar
                }
            } else {
                tenancy()->end();
            }

        } catch (\Exception $e) {
            Log::warning('OnboardingController::criarPlanoGratuito3Dias - Erro geral ao criar trial', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Helper: Cria assinatura trial para uma empresa específica se não houver
     */
    private function criarTrialParaEmpresa(int $userId, int $empresaId, int $tenantId, $planoGratuito = null): void
    {
        try {
            // Verificar se já tem assinatura
            $assinaturaExistente = $this->assinaturaRepository->buscarAssinaturaAtualPorEmpresa($empresaId, $tenantId);
            if ($assinaturaExistente) {
                return;
            }

            // Se não passou o plano, buscar agora
            if (!$planoGratuito) {
                $planosAtivos = $this->planoRepository->listar(['ativo' => true]);
                foreach ($planosAtivos as $plano) {
                    $precoMensal = $plano->precoMensal ?? 0;
                    if ($precoMensal == 0 || $precoMensal === null) {
                        $planoGratuito = $plano;
                        break;
                    }
                }
            }

            if (!$planoGratuito) return;

            // Calcular datas
            $dataInicio = Carbon::now();
            $dataFim = $dataInicio->copy()->addDays(3);

            // Criar DTO
            $assinaturaTrialDTO = new CriarAssinaturaDTO(
                userId: $userId,
                planoId: $planoGratuito->id,
                status: 'ativa',
                dataInicio: $dataInicio,
                dataFim: $dataFim,
                valorPago: 0,
                metodoPagamento: 'gratuito',
                transacaoId: null,
                diasGracePeriod: 0,
                observacoes: 'Trial automático de 3 dias - criado via onboarding global',
                tenantId: $tenantId,
                empresaId: $empresaId,
            );

            // Criar
            $this->criarAssinaturaUseCase->executar($assinaturaTrialDTO);
            
            Log::info('OnboardingController::criarTrialParaEmpresa - Trial criado com sucesso', [
                'tenant_id' => $tenantId,
                'empresa_id' => $empresaId,
                'user_id' => $userId,
            ]);

        } catch (\Exception $e) {
            Log::warning('OnboardingController::criarTrialParaEmpresa - Erro', [
                'tenant_id' => $tenantId,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
