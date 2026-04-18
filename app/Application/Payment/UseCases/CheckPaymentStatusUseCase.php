<?php

namespace App\Application\Payment\UseCases;

use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Domain\Payment\Entities\PaymentResult;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Consultar status de um pagamento
 */
class CheckPaymentStatusUseCase
{
    public function __construct(
        private PaymentProviderInterface $paymentProvider
    ) {}

    /**
     * Consulta o status de um pagamento no gateway
     * 
     * @param string $externalId ID do pagamento no gateway
     * @return PaymentResult
     */
    public function executar(string $externalId): PaymentResult
    {
        Log::info('Consultando status do pagamento via UseCase', [
            'external_id' => $externalId
        ]);

        return $this->paymentProvider->getPaymentStatus($externalId);
    }
}
