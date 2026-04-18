<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Modules\Assinatura\Models\Assinatura;
use App\Domain\Payment\Repositories\PaymentProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job para verificar status de pagamento pendente
 * 
 * Executa periodicamente para verificar se pagamentos pendentes foram aprovados
 */
class VerificarPagamentoPendenteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // 1 minuto entre tentativas

    public function __construct(
        private readonly int $assinaturaId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentProviderInterface $paymentProvider): void
    {
        Log::info('VerificarPagamentoPendenteJob iniciado', [
            'assinatura_id' => $this->assinaturaId,
        ]);

        $assinatura = Assinatura::find($this->assinaturaId);
        
        if (!$assinatura) {
            Log::warning('VerificarPagamentoPendenteJob - Assinatura não encontrada', [
                'assinatura_id' => $this->assinaturaId,
            ]);
            return;
        }

        // Se não está mais pendente, não precisa verificar
        if (!in_array($assinatura->status, ['suspensa', 'pendente'])) {
            Log::info('VerificarPagamentoPendenteJob - Assinatura não está mais pendente', [
                'assinatura_id' => $this->assinaturaId,
                'status_atual' => $assinatura->status,
            ]);
            return;
        }

        // Se não tem transação ID, não pode verificar
        if (!$assinatura->transacao_id) {
            Log::warning('VerificarPagamentoPendenteJob - Assinatura sem transação ID', [
                'assinatura_id' => $this->assinaturaId,
            ]);
            return;
        }

        try {
            // Verificar status no gateway
            $paymentResult = $paymentProvider->getPaymentStatus($assinatura->transacao_id);
            $status = $paymentResult->status;
            
            Log::info('VerificarPagamentoPendenteJob - Status verificado', [
                'assinatura_id' => $this->assinaturaId,
                'status_anterior' => $assinatura->status,
                'status_novo' => $status,
            ]);

            // Se foi aprovado, atualizar assinatura
            if ($status === 'approved' || $status === 'ativa') {
                $assinatura->status = 'ativa';
                $assinatura->save();
                
                Log::info('VerificarPagamentoPendenteJob - Assinatura ativada', [
                    'assinatura_id' => $this->assinaturaId,
                ]);

                // Notificar usuário (se houver email)
                if ($assinatura->user && $assinatura->user->email) {
                    try {
                        // Mail::to($assinatura->user->email)->send(new PagamentoAprovadoMail($assinatura));
                        Log::info('VerificarPagamentoPendenteJob - Email de aprovação enviado', [
                            'assinatura_id' => $this->assinaturaId,
                            'email' => $assinatura->user->email,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('VerificarPagamentoPendenteJob - Erro ao enviar email', [
                            'assinatura_id' => $this->assinaturaId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } 
            // Se foi rejeitado, atualizar status
            elseif ($status === 'rejected' || $status === 'cancelled') {
                $assinatura->status = 'cancelada';
                $assinatura->save();
                
                Log::warning('VerificarPagamentoPendenteJob - Assinatura cancelada', [
                    'assinatura_id' => $this->assinaturaId,
                    'status' => $status,
                ]);
            }
            // Se ainda está pendente, reagendar verificação
            elseif (in_array($status, ['pending', 'in_process', 'in_mediation'])) {
                // Reagendar para verificar novamente em 5 minutos
                self::dispatch($this->assinaturaId)
                    ->delay(now()->addMinutes(5))
                    ->onQueue('payments');
                
                Log::info('VerificarPagamentoPendenteJob - Reagendado para verificação futura', [
                    'assinatura_id' => $this->assinaturaId,
                    'status' => $status,
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('VerificarPagamentoPendenteJob - Erro ao verificar status', [
                'assinatura_id' => $this->assinaturaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Reagendar para tentar novamente
            throw $e; // Laravel vai fazer retry automaticamente
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('VerificarPagamentoPendenteJob - Falhou após todas as tentativas', [
            'assinatura_id' => $this->assinaturaId,
            'error' => $exception->getMessage(),
        ]);
        
        // Aqui você poderia notificar um admin ou criar um alerta
    }
}

