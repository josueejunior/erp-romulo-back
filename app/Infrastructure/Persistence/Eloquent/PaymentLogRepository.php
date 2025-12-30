<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Payment\Repositories\PaymentLogRepositoryInterface;
use App\Models\PaymentLog;

/**
 * Implementação do Repository de PaymentLog usando Eloquent
 */
class PaymentLogRepository implements PaymentLogRepositoryInterface
{
    public function buscarPorExternalId(string $externalId): ?PaymentLog
    {
        return PaymentLog::where('external_id', $externalId)->first();
    }

    public function criarOuAtualizar(array $dados): PaymentLog
    {
        $log = PaymentLog::where('external_id', $dados['external_id'] ?? null)->first();
        
        if ($log) {
            $log->update($dados);
            return $log->fresh();
        }
        
        return PaymentLog::create($dados);
    }
}

