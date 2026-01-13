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
        // Buscar onboarding
        $onboarding = $this->buscarOuFalhar($dto);

        // Validar que pode concluir
        if (!$onboarding->podeConcluir()) {
            throw new DomainException('Onboarding já está concluído.');
        }

        // Usar método da entidade para concluir
        $onboardingConcluido = $onboarding->concluir();

        // Persistir alterações
        $onboardingSalvo = $this->repository->atualizar($onboardingConcluido);

        Log::info('GerenciarOnboardingUseCase - Onboarding concluído', [
            'onboarding_id' => $onboardingSalvo->id,
            'tenant_id' => $onboardingSalvo->tenantId,
            'user_id' => $onboardingSalvo->userId,
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
        // Se tem onboarding_id, buscar por ID
        if ($dto->onboardingId !== null) {
            $onboarding = $this->repository->buscarPorId($dto->onboardingId);
            if (!$onboarding) {
                throw new DomainException('Onboarding não encontrado.');
            }
            return $onboarding;
        }

        // Caso contrário, buscar por critérios
        $onboarding = $this->repository->buscarPorCritérios(
            tenantId: $dto->tenantId,
            userId: $dto->userId,
            sessionId: $dto->sessionId,
            email: $dto->email,
        );

        if (!$onboarding) {
            throw new DomainException('Onboarding não encontrado. Inicie o onboarding primeiro.');
        }

        return $onboarding;
    }
}
