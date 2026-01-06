<?php

namespace App\Application\Assinatura\UseCases;

use App\Application\Assinatura\DTOs\CriarAssinaturaDTO;
use App\Domain\Assinatura\Entities\Assinatura;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use App\Modules\Assinatura\Models\Plano;
use App\Models\Tenant;
use App\Modules\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Criar Assinatura
 * Orquestra a criação de uma nova assinatura seguindo regras de negócio
 */
class CriarAssinaturaUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
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
        $tenant = null;
        if ($dto->tenantId) {
            $tenant = Tenant::find($dto->tenantId);
            if (!$tenant) {
                throw new DomainException('Tenant não encontrado.');
            }
        }

        // Criar entidade do domínio
        $assinatura = new Assinatura(
            id: null, // Nova assinatura
            userId: $dto->userId, // 🔥 NOVO: Assinatura pertence ao usuário
            tenantId: $dto->tenantId, // Opcional para compatibilidade
            planoId: $dto->planoId,
            status: $dto->status,
            dataInicio: $dto->dataInicio ?? Carbon::now(),
            dataFim: $dto->dataFim,
            dataCancelamento: null,
            valorPago: $dto->valorPago ?? 0,
            metodoPagamento: $dto->metodoPagamento ?? 'gratuito',
            transacaoId: $dto->transacaoId,
            diasGracePeriod: $dto->diasGracePeriod,
            observacoes: $dto->observacoes,
        );

        // Salvar usando repository
        $assinaturaSalva = $this->assinaturaRepository->salvar($assinatura);

        // Se tenant foi fornecido e for a primeira assinatura ou se for ativa, atualizar tenant (compatibilidade)
        if ($tenant && (!$tenant->assinatura_atual_id || $dto->status === 'ativa')) {
            $tenant->update([
                'plano_atual_id' => $plano->id,
                'assinatura_atual_id' => $assinaturaSalva->id,
            ]);

            Log::info('Assinatura criada e definida como atual do tenant (compatibilidade)', [
                'user_id' => $dto->userId,
                'tenant_id' => $dto->tenantId,
                'assinatura_id' => $assinaturaSalva->id,
                'plano_id' => $plano->id,
            ]);
        }

        Log::info('Assinatura criada para o usuário', [
            'user_id' => $dto->userId,
            'assinatura_id' => $assinaturaSalva->id,
            'plano_id' => $plano->id,
        ]);

        return $assinaturaSalva;
    }
}



