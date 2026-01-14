<?php

namespace App\Application\Assinatura\UseCases;

use App\Application\Assinatura\DTOs\CriarAssinaturaDTO;
use App\Domain\Assinatura\Entities\Assinatura;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Assinatura\Services\AssinaturaValidationService;
use App\Domain\Assinatura\Events\AssinaturaCriada;
use App\Domain\Shared\Events\EventDispatcherInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use App\Modules\Assinatura\Models\Plano;
use App\Modules\Auth\Models\User;
use App\Services\ApplicationContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Criar Assinatura
 * Orquestra a criação de uma nova assinatura seguindo regras de negócio
 * 
 * 🔥 ARQUITETURA LIMPA: Usa TenantRepository em vez de Eloquent direto
 * 🔒 ROBUSTEZ: Usa Domain Service para validações complexas
 */
class CriarAssinaturaUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private TenantRepositoryInterface $tenantRepository,
        private AssinaturaValidationService $validationService,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @param CriarAssinaturaDTO $dto DTO com dados da assinatura
     * @return Assinatura Entidade criada
     * @throws DomainException Se houver erro de validação ou regra de negócio
     */
    public function executar(CriarAssinaturaDTO $dto): Assinatura
    {
        // 🔥 NOVO: Validar que o usuário existe
        $user = User::find($dto->userId);
        if (!$user) {
            throw new DomainException('Usuário não encontrado.');
        }

        // Validar que o plano existe
        $plano = Plano::find($dto->planoId);
        if (!$plano) {
            throw new DomainException('Plano não encontrado.');
        }

        // Se tenantId foi fornecido, validar que existe (opcional)
        $tenantDomain = null;
        $tenantModel = null;
        if ($dto->tenantId) {
            $tenantDomain = $this->tenantRepository->buscarPorId($dto->tenantId);
            if (!$tenantDomain) {
                throw new DomainException('Tenant não encontrado.');
            }
            // Converter para Model apenas se precisar atualizar (compatibilidade)
            $tenantModel = $this->tenantRepository->buscarModeloPorId($dto->tenantId);
        }

        // 🔥 NOVO: Se empresaId não foi fornecido, tentar obter do usuário (empresa_ativa_id)
        $empresaId = $dto->empresaId;
        if (!$empresaId && $user->empresa_ativa_id) {
            $empresaId = $user->empresa_ativa_id;
            Log::info('CriarAssinaturaUseCase - Usando empresa_ativa_id do usuário', [
                'user_id' => $user->id,
                'empresa_id' => $empresaId,
            ]);
        }
        
        // 🔒 ROBUSTEZ: Validar empresa e plano existem, e não há conflito de assinatura ativa
        if ($empresaId) {
            $this->validationService->validarAntesDeCriar($empresaId, $dto->planoId);
        }

        // 🔥 CRÍTICO: Garantir que valorPago sempre seja o valor do plano
        // Se não foi fornecido ou é 0, usar o valor do plano
        $valorPago = $dto->valorPago;
        if (!$valorPago || $valorPago == 0) {
            $valorPago = $plano->preco_mensal ?? 0;
            Log::info('CriarAssinaturaUseCase - Valor pago preenchido com valor do plano', [
                'plano_id' => $plano->id,
                'valor_pago' => $valorPago,
            ]);
        }

        // Criar entidade do domínio
        $assinatura = new Assinatura(
            id: null, // Nova assinatura
            userId: $dto->userId,
            tenantId: $dto->tenantId, // Opcional para compatibilidade
            empresaId: $empresaId, // 🔥 NOVO: Assinatura pertence à empresa
            planoId: $dto->planoId,
            status: $dto->status,
            dataInicio: $dto->dataInicio ?? Carbon::now(),
            dataFim: $dto->dataFim,
            dataCancelamento: null,
            valorPago: $valorPago, // Sempre usar valor do plano ou fornecido
            metodoPagamento: $dto->metodoPagamento ?? 'gratuito',
            transacaoId: $dto->transacaoId,
            diasGracePeriod: $dto->diasGracePeriod,
            observacoes: $dto->observacoes,
        );

        // Salvar usando repository
        $assinaturaSalva = $this->assinaturaRepository->salvar($assinatura);

        // Se tenant foi fornecido e for a primeira assinatura ou se for ativa, atualizar tenant (compatibilidade)
        if ($tenantModel && (!$tenantModel->assinatura_atual_id || $dto->status === 'ativa')) {
            $tenantModel->update([
                'plano_atual_id' => $plano->id,
                'assinatura_atual_id' => $assinaturaSalva->id,
            ]);

        }

        // Buscar email do usuário para notificação
        $emailDestino = null;
        if ($user) {
            $emailDestino = $user->email;
        }

        // Disparar evento de assinatura criada
        $this->eventDispatcher->dispatch(
            new AssinaturaCriada(
                assinaturaId: $assinaturaSalva->id,
                tenantId: $dto->tenantId ?? 0,
                empresaId: $empresaId ?? 0,
                userId: $dto->userId,
                planoId: $dto->planoId,
                status: $dto->status,
                emailDestino: $emailDestino,
            )
        );

        // Limpar cache do ApplicationContext para forçar nova busca da assinatura
        try {
            $context = app(ApplicationContext::class);
            if ($context->isInitialized()) {
                $context->limparCacheAssinatura();
            }
        } catch (\Exception $e) {
            // Não falhar se não conseguir limpar cache
        }

        return $assinaturaSalva;
    }
}




