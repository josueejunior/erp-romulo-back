<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Assinatura\UseCases\CobrarAssinaturaExpiradaUseCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job para processar cobrança recorrente em background
 * 
 * 🔥 MELHORIA: Fire and Forget - Processa cobrança assincronamente
 * 
 * Raciocínio: Evita bloquear o cron job principal e permite retry automático em caso de falha
 */
class ProcessarCobrancaRecorrente implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de tentativas
     */
    public int $tries = 3;

    /**
     * Timeout em segundos
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $assinaturaId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CobrarAssinaturaExpiradaUseCase $cobrarAssinaturaUseCase): void
    {
        Log::info('ProcessarCobrancaRecorrente - Iniciando processamento', [
            'tenant_id' => $this->tenantId,
            'assinatura_id' => $this->assinaturaId,
            'attempt' => $this->attempts(),
        ]);

        try {
            $resultado = $cobrarAssinaturaUseCase->executar(
                tenantId: $this->tenantId,
                assinaturaId: $this->assinaturaId
            );

            if ($resultado['sucesso']) {
                Log::info('ProcessarCobrancaRecorrente - Cobrança processada com sucesso', [
                    'tenant_id' => $this->tenantId,
                    'assinatura_id' => $this->assinaturaId,
                ]);
            } else {
                Log::warning('ProcessarCobrancaRecorrente - Cobrança não realizada', [
                    'tenant_id' => $this->tenantId,
                    'assinatura_id' => $this->assinaturaId,
                    'motivo' => $resultado['motivo'] ?? 'Desconhecido',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('ProcessarCobrancaRecorrente - Erro ao processar cobrança', [
                'tenant_id' => $this->tenantId,
                'assinatura_id' => $this->assinaturaId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw para permitir retry automático
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessarCobrancaRecorrente - Job falhou após todas as tentativas', [
            'tenant_id' => $this->tenantId,
            'assinatura_id' => $this->assinaturaId,
            'error' => $exception->getMessage(),
        ]);
    }
}


