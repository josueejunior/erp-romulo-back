<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Payment\Entities\PaymentResult;
use App\Domain\Payment\Repositories\PaymentLogRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Models\AfiliadoReferencia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Atualizar Assinatura via Webhook
 */
class AtualizarAssinaturaViaWebhookUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private PaymentLogRepositoryInterface $paymentLogRepository,
    ) {}

    /**
     * Executa o caso de uso
     * 
     * @param string $transacaoId ID da transação no gateway
     * @param PaymentResult $paymentResult Resultado do pagamento do webhook
     * @return void
     */
    public function executar(string $transacaoId, PaymentResult $paymentResult): void
    {
        // Buscar assinatura pelo external_id usando repository DDD
        $assinatura = $this->assinaturaRepository->buscarModeloPorTransacaoId($transacaoId);

        if (!$assinatura) {
            throw new NotFoundException("Assinatura não encontrada para transação: {$transacaoId}");
        }

        DB::transaction(function () use ($assinatura, $paymentResult, $transacaoId) {
            if ($paymentResult->isApproved() && $assinatura->status !== 'ativa') {
                // CRÍTICO: Cancelar outras assinaturas ativas do mesmo tenant antes de ativar a nova
                $assinaturasAntigas = \App\Modules\Assinatura\Models\Assinatura::where('tenant_id', $assinatura->tenant_id)
                    ->where('status', 'ativa')
                    ->where('id', '!=', $assinatura->id)
                    ->get();
                
                foreach ($assinaturasAntigas as $assinaturaAntiga) {
                    $assinaturaAntiga->update([
                        'status' => 'cancelada',
                        'data_cancelamento' => now(),
                        'observacoes' => ($assinaturaAntiga->observacoes ?? '') . 
                            "\n\nCancelada automaticamente por upgrade de plano via webhook em " . now()->format('d/m/Y H:i:s'),
                    ]);
                    
                    Log::info('Assinatura antiga cancelada via webhook por upgrade', [
                        'assinatura_antiga_id' => $assinaturaAntiga->id,
                        'plano_antigo_id' => $assinaturaAntiga->plano_id,
                        'tenant_id' => $assinatura->tenant_id,
                    ]);
                }

                // Ativar assinatura
                $assinatura->update([
                    'status' => 'ativa',
                    'data_inicio' => $paymentResult->approvedAt ?? now(),
                ]);

                // CRÍTICO: Atualizar tenant com plano e assinatura atuais
                $tenant = $assinatura->tenant;
                if ($tenant) {
                    $tenant->update([
                        'plano_atual_id' => $assinatura->plano_id,
                        'assinatura_atual_id' => $assinatura->id,
                    ]);
                    
                    // Forçar reload para garantir atualização
                    $tenant->refresh();
                    
                    Log::info('Tenant atualizado via webhook', [
                        'tenant_id' => $tenant->id,
                        'plano_atual_id' => $tenant->plano_atual_id,
                        'assinatura_atual_id' => $tenant->assinatura_atual_id,
                    ]);
                }

                Log::info('Assinatura ativada via webhook', [
                    'assinatura_id' => $assinatura->id,
                    'tenant_id' => $assinatura->tenant_id,
                    'plano_id' => $assinatura->plano_id,
                    'external_id' => $transacaoId,
                    'assinaturas_antigas_canceladas' => $assinaturasAntigas->count(),
                ]);
                
                // Marcar flag cupom_aplicado se houver referência de afiliado pendente
                // Buscar referência vinculada ao tenant que ainda não teve cupom aplicado
                $referenciaAfiliado = AfiliadoReferencia::where('tenant_id', $tenant->id)
                    ->where('cadastro_concluido', true)
                    ->where('cupom_aplicado', false)
                    ->orderBy('cadastro_concluido_em', 'desc')
                    ->first();
                
                if ($referenciaAfiliado) {
                    $referenciaAfiliado->update([
                        'cupom_aplicado' => true,
                    ]);
                    
                    Log::info('Flag cupom_aplicado marcada via webhook (pagamento confirmado)', [
                        'referencia_id' => $referenciaAfiliado->id,
                        'afiliado_id' => $referenciaAfiliado->afiliado_id,
                        'tenant_id' => $tenant->id,
                        'assinatura_id' => $assinatura->id,
                    ]);
                }
            } elseif ($paymentResult->isRejected()) {
                // Marcar como suspensa se rejeitado
                $assinatura->update([
                    'status' => 'suspensa',
                    'observacoes' => ($assinatura->observacoes ?? '') . "\nPagamento rejeitado via webhook em " . now()->format('d/m/Y H:i:s') . ": {$paymentResult->errorMessage}",
                ]);

                Log::warning('Assinatura suspensa via webhook (pagamento rejeitado)', [
                    'assinatura_id' => $assinatura->id,
                    'external_id' => $transacaoId,
                    'error' => $paymentResult->errorMessage,
                ]);
            } elseif ($paymentResult->isPending()) {
                // PIX ou outro método pendente - apenas logar
                Log::info('Pagamento pendente via webhook', [
                    'assinatura_id' => $assinatura->id,
                    'external_id' => $transacaoId,
                    'status' => $paymentResult->status,
                ]);
            }

            // Atualizar log de pagamento usando repository DDD
            $paymentLog = $this->paymentLogRepository->buscarPorExternalId($transacaoId);
            if ($paymentLog) {
                $this->paymentLogRepository->criarOuAtualizar([
                    'external_id' => $transacaoId,
                    'status' => $paymentResult->status,
                    'dados_resposta' => array_merge($paymentLog->dados_resposta ?? [], [
                        'webhook_status' => $paymentResult->status,
                        'webhook_received_at' => now()->toIso8601String(),
                    ]),
                ]);
            }
        });
    }
}



