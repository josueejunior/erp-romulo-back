<?php

declare(strict_types=1);

namespace App\Domain\Shared\Services;

use Illuminate\Support\Facades\Log;

/**
 * Serviço de Retry com Exponential Backoff
 * 
 * Domain Service - Contém lógica de domínio para retry
 * Segue DDD: não conhece detalhes de infraestrutura
 */
final class RetryService
{
    /**
     * Executa uma operação com retry e exponential backoff
     * 
     * @param callable $operation Operação a executar
     * @param int $maxAttempts Número máximo de tentativas
     * @param int $initialDelay Delay inicial em segundos
     * @param float $backoffMultiplier Multiplicador para exponential backoff
     * @return mixed Resultado da operação
     * @throws \Exception Se todas as tentativas falharem
     */
    public static function withRetry(
        callable $operation,
        int $maxAttempts = 3,
        int $initialDelay = 1,
        float $backoffMultiplier = 2.0
    ): mixed {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $attempt++;
                $lastException = $e;

                // Se é a última tentativa, não aguardar
                if ($attempt >= $maxAttempts) {
                    Log::error("Retry falhou após {$maxAttempts} tentativas", [
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                // Calcular delay com exponential backoff
                $delay = (int) ($initialDelay * pow($backoffMultiplier, $attempt - 1));
                
                Log::warning("Tentativa {$attempt}/{$maxAttempts} falhou, aguardando {$delay}s antes de retry", [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'delay' => $delay,
                    'error' => $e->getMessage(),
                ]);

                sleep($delay);
            }
        }

        throw $lastException ?? new \RuntimeException('Retry falhou sem exceção capturada');
    }
}

