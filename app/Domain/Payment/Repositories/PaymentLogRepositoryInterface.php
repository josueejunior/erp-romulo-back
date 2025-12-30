<?php

namespace App\Domain\Payment\Repositories;

/**
 * Interface do Repository de PaymentLog
 * Para logs de pagamento (auditoria)
 */
interface PaymentLogRepositoryInterface
{
    /**
     * Buscar log por external_id
     * 
     * @param string $externalId ID da transação no gateway
     * @return \App\Models\PaymentLog|null
     */
    public function buscarPorExternalId(string $externalId): ?\App\Models\PaymentLog;

    /**
     * Criar ou atualizar log de pagamento
     * 
     * @param array $dados Dados do log
     * @return \App\Models\PaymentLog
     */
    public function criarOuAtualizar(array $dados): \App\Models\PaymentLog;
}

