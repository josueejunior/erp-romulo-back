<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Assinatura\Models\Assinatura;
use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Application\Assinatura\UseCases\AtualizarAssinaturaViaWebhookUseCase;
use App\Domain\Payment\Repositories\PaymentLogRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Comando para verificar pagamentos pendentes e atualizar status
 * 
 * Executa periodicamente para:
 * - Buscar assinaturas com status "suspensa" que têm transacao_id (pagamento pendente)
 * - Consultar status no Mercado Pago
 * - Atualizar assinatura se foi aprovado/rejeitado
 * 
 * Serve como fallback caso o webhook não seja recebido
 */
class VerificarPagamentosPendentes extends Command
{
    protected $signature = 'pagamentos:verificar-pendentes 
                            {--horas=24 : Verificar pagamentos pendentes há mais de X horas}';

    protected $description = 'Verifica pagamentos pendentes no Mercado Pago e atualiza assinaturas';

    public function __construct(
        private PaymentProviderInterface $paymentProvider,
        private AtualizarAssinaturaViaWebhookUseCase $atualizarAssinaturaUseCase,
        private PaymentLogRepositoryInterface $paymentLogRepository,
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $horas = (int) $this->option('horas');
        $this->info("🔍 Verificando pagamentos pendentes há mais de {$horas} hora(s)...");

        // Buscar assinaturas suspensas com transacao_id (pagamento pendente)
        // Criadas há mais de X horas (para evitar consultar imediatamente após criação)
        $dataLimite = Carbon::now()->subHours($horas);
        
        $assinaturasPendentes = Assinatura::where('status', 'suspensa')
            ->whereNotNull('transacao_id')
            ->where('created_at', '<=', $dataLimite)
            ->where('created_at', '>=', Carbon::now()->subDays(7)) // Apenas dos últimos 7 dias
            ->with(['tenant', 'plano'])
            ->get();

        if ($assinaturasPendentes->isEmpty()) {
            $this->info("✅ Nenhuma assinatura pendente encontrada.");
            return 0;
        }

        $this->info("📋 Encontradas {$assinaturasPendentes->count()} assinatura(s) pendente(s) para verificar.");

        $totalProcessadas = 0;
        $totalAtivadas = 0;
        $totalRejeitadas = 0;
        $totalAindaPendentes = 0;
        $totalErros = 0;

        foreach ($assinaturasPendentes as $assinatura) {
            try {
                $this->line("  🔄 Verificando assinatura #{$assinatura->id} (Tenant: {$assinatura->tenant->razao_social})");
                $this->line("     Transação: {$assinatura->transacao_id}");
                $this->line("     Plano: {$assinatura->plano->nome}");
                
                // Consultar status no Mercado Pago
                $paymentResult = $this->paymentProvider->getPaymentStatus($assinatura->transacao_id);
                
                $this->line("     Status no MP: {$paymentResult->status}");
                
                // Se foi aprovado, atualizar assinatura
                if ($paymentResult->isApproved()) {
                    $this->info("     ✅ Pagamento aprovado! Ativando assinatura...");
                    
                    $this->atualizarAssinaturaUseCase->executar(
                        $assinatura->transacao_id,
                        $paymentResult
                    );
                    
                    $totalAtivadas++;
                    $this->info("     ✅ Assinatura ativada com sucesso!");
                }
                // Se foi rejeitado, marcar como rejeitado
                elseif ($paymentResult->isRejected()) {
                    $this->warn("     ❌ Pagamento rejeitado: {$paymentResult->errorMessage}");
                    
                    $assinatura->update([
                        'status' => 'suspensa',
                        'observacoes' => ($assinatura->observacoes ?? '') . 
                            "\n\nPagamento rejeitado após verificação em " . now()->format('d/m/Y H:i:s') . 
                            ": {$paymentResult->errorMessage}",
                    ]);
                    
                    // Atualizar log de pagamento
                    $paymentLog = $this->paymentLogRepository->buscarPorExternalId($assinatura->transacao_id);
                    if ($paymentLog) {
                        $dadosResposta = array_merge($paymentLog->dados_resposta ?? [], [
                            'verificacao_status' => $paymentResult->status,
                            'verificacao_em' => now()->toIso8601String(),
                            'error_message' => $paymentResult->errorMessage,
                        ]);
                        
                        $paymentLog->update([
                            'status' => $paymentResult->status,
                            'dados_resposta' => $dadosResposta,
                        ]);
                    }
                    
                    $totalRejeitadas++;
                }
                // Se ainda está pendente, apenas logar
                elseif ($paymentResult->isPending()) {
                    $this->line("     ⏳ Ainda pendente - aguardando...");
                    $totalAindaPendentes++;
                    
                    // Atualizar log para registrar que verificamos
                    $paymentLog = $this->paymentLogRepository->buscarPorExternalId($assinatura->transacao_id);
                    if ($paymentLog) {
                        $dadosResposta = array_merge($paymentLog->dados_resposta ?? [], [
                            'ultima_verificacao' => now()->toIso8601String(),
                            'verificacao_status' => $paymentResult->status,
                        ]);
                        
                        $paymentLog->update([
                            'status' => $paymentResult->status,
                            'dados_resposta' => $dadosResposta,
                        ]);
                    }
                }
                
                $totalProcessadas++;
                
            } catch (\App\Domain\Exceptions\NotFoundException $e) {
                $this->error("     ❌ Pagamento não encontrado no Mercado Pago: {$e->getMessage()}");
                $totalErros++;
                Log::warning('Pagamento não encontrado ao verificar pendente', [
                    'assinatura_id' => $assinatura->id,
                    'transacao_id' => $assinatura->transacao_id,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Exception $e) {
                $this->error("     ❌ Erro ao verificar pagamento: {$e->getMessage()}");
                $totalErros++;
                Log::error('Erro ao verificar pagamento pendente', [
                    'assinatura_id' => $assinatura->id,
                    'transacao_id' => $assinatura->transacao_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            
            // Pequeno delay para não sobrecarregar a API do Mercado Pago
            usleep(500000); // 0.5 segundos
        }

        // Resumo
        $this->newLine();
        $this->info("📊 Resumo da verificação:");
        $this->line("   Total processadas: {$totalProcessadas}");
        $this->info("   ✅ Aprovadas e ativadas: {$totalAtivadas}");
        $this->warn("   ❌ Rejeitadas: {$totalRejeitadas}");
        $this->line("   ⏳ Ainda pendentes: {$totalAindaPendentes}");
        if ($totalErros > 0) {
            $this->error("   ❌ Erros: {$totalErros}");
        }

        return 0;
    }
}

