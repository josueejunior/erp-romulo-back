<?php

declare(strict_types=1);

namespace App\Application\Onboarding\UseCases;

use App\Domain\Onboarding\Entities\OnboardingProgress;
use App\Domain\Onboarding\Repositories\OnboardingProgressRepositoryInterface;
use App\Application\Onboarding\DTOs\IniciarOnboardingDTO;
use App\Application\Onboarding\DTOs\MarcarEtapaDTO;
use App\Application\Onboarding\DTOs\MarcarChecklistItemDTO;
use App\Application\Onboarding\DTOs\ConcluirOnboardingDTO;
use App\Application\Onboarding\DTOs\BuscarProgressoDTO;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Use Case: Gerenciar Onboarding
 * 
 * ✅ DDD: Usa Repository Interface em vez de Eloquent direto
 * ✅ DDD: Usa DTOs para entrada
 * ✅ DDD: Retorna entidades de domínio
 * ✅ DDD: Contém apenas lógica de negócio
 */
final class GerenciarOnboardingUseCase
{
    public function __construct(
        private readonly OnboardingProgressRepositoryInterface $repository,
    ) {}

    /**
     * Inicia ou retoma onboarding
     * 
     * @return OnboardingProgress Entidade de domínio
     */
    public function iniciar(IniciarOnboardingDTO $dto): OnboardingProgress
    {
        // Verificar se já existe onboarding não concluído
        $existente = $this->repository->buscarNaoConcluidoPorCritérios(
            tenantId: $dto->tenantId,
            userId: $dto->userId,
            sessionId: $dto->sessionId,
            email: $dto->email,
        );

        if ($existente) {
            return $existente;
        }

        // Criar novo onboarding
        $novoOnboarding = new OnboardingProgress(
            id: null,
            tenantId: $dto->tenantId,
            userId: $dto->userId,
            sessionId: $dto->sessionId,
            email: $dto->email,
            onboardingConcluido: false,
            etapasConcluidas: [],
            checklist: [],
            progressoPercentual: 0,
            iniciadoEm: Carbon::now(),
            concluidoEm: null,
        );

        return $this->repository->criar($novoOnboarding);
    }

    /**
     * Marca uma etapa como concluída
     * 
     * 🔥 MELHORIA: Calcula automaticamente próxima etapa recomendada
     * 
     * @return OnboardingProgress Entidade de domínio
     */
    public function marcarEtapaConcluida(MarcarEtapaDTO $dto): OnboardingProgress
    {
        // Buscar onboarding
        $onboarding = $this->buscarOuFalhar($dto);

        // Usar método da entidade para adicionar etapa
        $onboardingAtualizado = $onboarding->adicionarEtapaConcluida($dto->etapa);

        // Persistir alterações
        $onboardingSalvo = $this->repository->atualizar($onboardingAtualizado);

        // 🔥 MELHORIA: Calcular próxima etapa recomendada
        $todasEtapas = ['welcome', 'dashboard', 'processos', 'orcamentos', 'fornecedores', 'documentos', 'orgaos', 'setores', 'complete'];
        $proximaEtapa = $onboardingSalvo->getProximaEtapaRecomendada($todasEtapas);

        Log::info('GerenciarOnboardingUseCase - Etapa concluída', [
            'onboarding_id' => $onboardingSalvo->id,
            'etapa' => $dto->etapa,
            'progresso' => $onboardingSalvo->progressoPercentual,
            'next_recommended_step' => $proximaEtapa, // 🔥 NOVO: Próxima etapa recomendada
        ]);

        return $onboardingSalvo;
    }

    /**
     * Marca item do checklist como concluído
     * 
     * @return OnboardingProgress Entidade de domínio
     */
    public function marcarChecklistItem(MarcarChecklistItemDTO $dto): OnboardingProgress
    {
        // Buscar onboarding
        $onboarding = $this->buscarOuFalhar($dto);

        // Usar método da entidade para marcar item
        $onboardingAtualizado = $onboarding->marcarItemChecklist($dto->item);

        // Persistir alterações
        return $this->repository->atualizar($onboardingAtualizado);
    }

    /**
     * Conclui o onboarding
     * 
     * @return OnboardingProgress Entidade de domínio
     */
    public function concluir(ConcluirOnboardingDTO $dto): OnboardingProgress
    {
        Log::info('GerenciarOnboardingUseCase::concluir - INÍCIO', [
            'dto_onboardingId' => $dto->onboardingId,
            'dto_tenantId' => $dto->tenantId,
            'dto_userId' => $dto->userId,
            'dto_sessionId' => $dto->sessionId,
            'dto_email' => $dto->email,
        ]);

        // Buscar onboarding
        $onboarding = $this->buscarOuFalhar($dto);

        Log::info('GerenciarOnboardingUseCase::concluir - Onboarding encontrado', [
            'onboarding_id' => $onboarding->id,
            'onboarding_concluido' => $onboarding->onboardingConcluido,
        ]);

        // Validar que pode concluir
        if (!$onboarding->podeConcluir()) {
            Log::warning('GerenciarOnboardingUseCase::concluir - Onboarding já está concluído', [
                'onboarding_id' => $onboarding->id,
            ]);
            throw new DomainException('Onboarding já está concluído.');
        }

        // Usar método da entidade para concluir
        $onboardingConcluido = $onboarding->concluir();

        Log::info('GerenciarOnboardingUseCase::concluir - Onboarding marcado como concluído na entidade', [
            'onboarding_id' => $onboardingConcluido->id,
            'concluido_em' => $onboardingConcluido->concluidoEm?->toIso8601String(),
        ]);

        // Persistir alterações
        $onboardingSalvo = $this->repository->atualizar($onboardingConcluido);

        Log::info('GerenciarOnboardingUseCase::concluir - Onboarding concluído e persistido', [
            'onboarding_id' => $onboardingSalvo->id,
            'tenant_id' => $onboardingSalvo->tenantId,
            'user_id' => $onboardingSalvo->userId,
            'concluido_em' => $onboardingSalvo->concluidoEm?->toIso8601String(),
        ]);

        return $onboardingSalvo;
    }

    /**
     * Verifica se onboarding está concluído
     */
    public function estaConcluido(BuscarProgressoDTO $dto): bool
    {
        return $this->repository->existeConcluidoPorCritérios(
            tenantId: $dto->tenantId,
            userId: $dto->userId,
            sessionId: $dto->sessionId,
            email: $dto->email,
        );
    }

    /**
     * Busca progresso atual
     * 
     * @return OnboardingProgress|null Entidade de domínio
     */
    public function buscarProgresso(BuscarProgressoDTO $dto): ?OnboardingProgress
    {
        return $this->repository->buscarPorCritérios(
            tenantId: $dto->tenantId,
            userId: $dto->userId,
            sessionId: $dto->sessionId,
            email: $dto->email,
        );
    }

    /**
     * Helper: Busca onboarding ou lança exceção
     * 
     * @param MarcarEtapaDTO|MarcarChecklistItemDTO|ConcluirOnboardingDTO $dto
     * @throws DomainException se onboarding não encontrado
     */
    private function buscarOuFalhar(MarcarEtapaDTO|MarcarChecklistItemDTO|ConcluirOnboardingDTO $dto): OnboardingProgress
    {
        Log::info('GerenciarOnboardingUseCase::buscarOuFalhar - INÍCIO', [
            'dto_onboardingId' => $dto->onboardingId ?? null,
            'dto_tenantId' => $dto->tenantId ?? null,
            'dto_userId' => $dto->userId ?? null,
            'dto_sessionId' => $dto->sessionId ?? null,
            'dto_email' => $dto->email ?? null,
        ]);

        // Se tem onboarding_id, buscar por ID
        if ($dto->onboardingId !== null) {
            Log::info('GerenciarOnboardingUseCase::buscarOuFalhar - Buscando por ID', [
                'onboarding_id' => $dto->onboardingId,
            ]);
            $onboarding = $this->repository->buscarPorId($dto->onboardingId);
            if (!$onboarding) {
                Log::warning('GerenciarOnboardingUseCase::buscarOuFalhar - Onboarding não encontrado por ID', [
                    'onboarding_id' => $dto->onboardingId,
                ]);
                throw new DomainException('Onboarding não encontrado.');
            }
            Log::info('GerenciarOnboardingUseCase::buscarOuFalhar - Onboarding encontrado por ID', [
                'onboarding_id' => $onboarding->id,
            ]);
            return $onboarding;
        }

        // Caso contrário, buscar por critérios
        Log::info('GerenciarOnboardingUseCase::buscarOuFalhar - Buscando por critérios', [
            'tenantId' => $dto->tenantId,
            'userId' => $dto->userId,
            'sessionId' => $dto->sessionId,
            'email' => $dto->email,
        ]);

        $onboarding = $this->repository->buscarPorCritérios(
            tenantId: $dto->tenantId,
            userId: $dto->userId,
            sessionId: $dto->sessionId,
            email: $dto->email,
        );

        if (!$onboarding) {
            Log::warning('GerenciarOnboardingUseCase::buscarOuFalhar - Onboarding não encontrado por critérios', [
                'tenantId' => $dto->tenantId,
                'userId' => $dto->userId,
                'sessionId' => $dto->sessionId,
                'email' => $dto->email,
            ]);
            throw new DomainException('Onboarding não encontrado. Inicie o onboarding primeiro.');
        }

        Log::info('GerenciarOnboardingUseCase::buscarOuFalhar - Onboarding encontrado por critérios', [
            'onboarding_id' => $onboarding->id,
        ]);

        return $onboarding;
    }
}
