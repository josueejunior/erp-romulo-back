<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use App\Domain\Payment\Repositories\PaymentProviderInterface;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Associa um cartão (Mercado Pago Customer + Card) à assinatura atual da empresa ativa.
 */
class SalvarCartaoAssinaturaUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private PaymentProviderInterface $paymentProvider,
        private BuscarTenantDoUsuarioUseCase $buscarTenantDoUsuarioUseCase,
    ) {}

    public function executar(
        Authenticatable $user,
        string $cardToken,
        string $payerEmail,
        ?string $payerCpf,
    ): void {
        $tenant = $this->buscarTenantDoUsuarioUseCase->executar($user);

        if (!$tenant || !$user->empresa_ativa_id) {
            throw new DomainException('Nenhuma empresa ativa encontrada. Selecione uma empresa para cadastrar o cartão.');
        }

        $assinatura = $this->assinaturaRepository->buscarAssinaturaAtualPorEmpresa(
            (int) $user->empresa_ativa_id,
            (int) $tenant->id
        );

        if (!$assinatura || !$assinatura->id) {
            throw new DomainException('Nenhuma assinatura encontrada. Contrate um plano antes de cadastrar o cartão.');
        }

        $model = $this->assinaturaRepository->buscarModeloPorId($assinatura->id);
        $existingCustomerId = $model?->mercado_pago_customer_id
            ? trim((string) $model->mercado_pago_customer_id)
            : null;
        if ($existingCustomerId === '') {
            $existingCustomerId = null;
        }

        $ids = $this->paymentProvider->createCustomerAndCard(
            email: strtolower(trim($payerEmail)),
            cardToken: $cardToken,
            cpf: $payerCpf,
            existingCustomerId: $existingCustomerId,
        );

        $this->assinaturaRepository->atualizarMercadoPagoVault(
            $assinatura->id,
            $ids['customer_id'],
            $ids['card_id'],
            'credit_card',
        );
    }
}
