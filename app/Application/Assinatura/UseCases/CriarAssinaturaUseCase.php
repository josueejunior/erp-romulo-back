<?php

namespace App\Application\Assinatura\UseCases;

use App\Application\Assinatura\DTOs\CriarAssinaturaDTO;
use App\Domain\Assinatura\Entities\Assinatura;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use App\Modules\Assinatura\Models\Plano;
use App\Models\Tenant;
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
        // Validar que o tenant existe
        $tenant = Tenant::find($dto->tenantId);
        if (!$tenant) {
            throw new DomainException('Tenant não encontrado.');
        }

        // Validar que o plano existe
        $plano = Plano::find($dto->planoId);
        if (!$plano) {
            throw new DomainException('Plano não encontrado.');
        }

        // Criar entidade do domínio
        $assinatura = new Assinatura(
            id: null, // Nova assinatura
            tenantId: $dto->tenantId,
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

        // Se for a primeira assinatura ou se for ativa, atualizar tenant
        if (!$tenant->assinatura_atual_id || $dto->status === 'ativa') {
            $tenant->update([
                'plano_atual_id' => $plano->id,
                'assinatura_atual_id' => $assinaturaSalva->id,
            ]);

            Log::info('Assinatura criada e definida como atual do tenant', [
                'tenant_id' => $dto->tenantId,
                'assinatura_id' => $assinaturaSalva->id,
                'plano_id' => $plano->id,
            ]);
        }

        return $assinaturaSalva;
    }
}

