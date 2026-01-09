<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Circuit Breaker Pattern
 * 
 * Previne chamadas repetidas a serviços externos quando estão falhando
 * Estados: CLOSED (normal), OPEN (falhando), HALF_OPEN (testando recuperação)
 */
class CircuitBreaker
{
    private string $serviceName;
    private int $failureThreshold;
    private int $timeout;
    private int $halfOpenTimeout;

    public function __construct(
        string $serviceName,
        int $failureThreshold = 5,
        int $timeout = 60, // segundos
        int $halfOpenTimeout = 30 // segundos
    ) {
        $this->serviceName = $serviceName;
        $this->failureThreshold = $failureThreshold;
        $this->timeout = $timeout;
        $this->halfOpenTimeout = $halfOpenTimeout;
    }

    /**
     * Executa uma chamada protegida pelo circuit breaker
     */
    public function call(callable $operation, callable $fallback = null)
    {
        $state = $this->getState();

        if ($state === 'OPEN') {
            Log::warning("Circuit breaker OPEN para {$this->serviceName} - usando fallback");
            
            if ($fallback) {
                return $fallback();
            }
            
            throw new \RuntimeException("Serviço {$this->serviceName} está temporariamente indisponível. Tente novamente mais tarde.");
        }

        try {
            $result = $operation();
            
            // Sucesso - resetar contador de falhas
            if ($state === 'HALF_OPEN') {
                $this->setState('CLOSED');
                Log::info("Circuit breaker fechado para {$this->serviceName} - serviço recuperado");
            }
            
            $this->resetFailureCount();
            return $result;
            
        } catch (\Exception $e) {
            $this->recordFailure();
            
            // Se atingiu threshold, abrir circuito
            if ($this->getFailureCount() >= $this->failureThreshold) {
                $this->setState('OPEN');
                Log::error("Circuit breaker ABERTO para {$this->serviceName} após {$this->failureThreshold} falhas");
            }
            
            // Se tem fallback, usar
            if ($fallback) {
                Log::warning("Usando fallback para {$this->serviceName} devido a erro: " . $e->getMessage());
                return $fallback();
            }
            
            throw $e;
        }
    }

    /**
     * Obtém estado atual do circuit breaker
     */
    private function getState(): string
    {
        $stateKey = "circuit_breaker:{$this->serviceName}:state";
        $state = Cache::get($stateKey, 'CLOSED');
        $stateTimeKey = "circuit_breaker:{$this->serviceName}:state_time";
        $stateTime = Cache::get($stateTimeKey, 0);

        // Se está OPEN há mais tempo que timeout, mudar para HALF_OPEN
        if ($state === 'OPEN' && (time() - $stateTime) >= $this->timeout) {
            $this->setState('HALF_OPEN');
            return 'HALF_OPEN';
        }

        // Se está HALF_OPEN há muito tempo, fechar
        if ($state === 'HALF_OPEN' && (time() - $stateTime) >= $this->halfOpenTimeout) {
            $this->setState('CLOSED');
            return 'CLOSED';
        }

        return $state;
    }

    /**
     * Define estado do circuit breaker
     */
    private function setState(string $state): void
    {
        $stateKey = "circuit_breaker:{$this->serviceName}:state";
        $stateTimeKey = "circuit_breaker:{$this->serviceName}:state_time";
        
        Cache::put($stateKey, $state, 3600); // 1 hora
        Cache::put($stateTimeKey, time(), 3600);
    }

    /**
     * Registra uma falha
     */
    private function recordFailure(): void
    {
        $key = "circuit_breaker:{$this->serviceName}:failures";
        $failures = Cache::get($key, 0);
        Cache::put($key, $failures + 1, 300); // 5 minutos
    }

    /**
     * Obtém contador de falhas
     */
    private function getFailureCount(): int
    {
        $key = "circuit_breaker:{$this->serviceName}:failures";
        return Cache::get($key, 0);
    }

    /**
     * Reseta contador de falhas
     */
    private function resetFailureCount(): void
    {
        $key = "circuit_breaker:{$this->serviceName}:failures";
        Cache::forget($key);
    }

    /**
     * Força reset do circuit breaker (útil para testes ou recovery manual)
     */
    public function reset(): void
    {
        $this->setState('CLOSED');
        $this->resetFailureCount();
    }
}

