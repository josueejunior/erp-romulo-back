<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Assinatura\UseCases\AtualizarAssinaturaViaWebhookUseCase;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Payment\Repositories\PaymentProviderInterface;
use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job assíncrono que processa um webhook do Mercado Pago já persistido em
 * `webhook_events`. Disparado pelo WebhookController depois de responder
 * 200 OK ao Mercado Pago, evitando exceder o timeout de 22s do provider.
 *
 * Contrato de idempotência:
 *  - O controller já salvou o evento com status `received`.
 *  - Este job passa para `processing`, executa o use case e marca `processed`.
 *  - Se falhar, registra `last_error` e incrementa `attempts`. Em caso de
 *    exceção, o Laravel re-enfileira (até $tries vezes).
 */
class ProcessarWebhookMercadoPagoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Tentativas totais antes de considerar falhado. */
    public int $tries = 5;

    /** Backoff exponencial (em segundos) entre tentativas. */
    public function backoff(): array
    {
        return [30, 60, 180, 600, 1800]; // 30s, 1min, 3min, 10min, 30min
    }

    public function __construct(
        private readonly int $webhookEventId,
    ) {}

    public function handle(
        PaymentProviderInterface $paymentProvider,
        AtualizarAssinaturaViaWebhookUseCase $atualizarAssinaturaViaWebhookUseCase,
    ): void {
        $event = WebhookEvent::find($this->webhookEventId);

        if (!$event) {
            Log::warning('ProcessarWebhookMercadoPagoJob: evento não encontrado', [
                'webhook_event_id' => $this->webhookEventId,
            ]);
            return;
        }

        if ($event->status === WebhookEvent::STATUS_PROCESSED) {
            // Já processado anteriormente — idempotência.
            Log::info('ProcessarWebhookMercadoPagoJob: evento já processado, pulando', [
                'webhook_event_id' => $event->id,
                'resource_id' => $event->resource_id,
            ]);
            return;
        }

        $event->update([
            'status' => WebhookEvent::STATUS_PROCESSING,
            'attempts' => $event->attempts + 1,
        ]);

        try {
            $paymentResult = $paymentProvider->processWebhook($event->payload);

            $atualizarAssinaturaViaWebhookUseCase->executar(
                $paymentResult->externalId,
                $paymentResult,
            );

            $event->update([
                'status' => WebhookEvent::STATUS_PROCESSED,
                'last_error' => null,
                'processed_at' => now(),
            ]);

            Log::info('Webhook MP processado com sucesso', [
                'webhook_event_id' => $event->id,
                'resource_id' => $event->resource_id,
                'payment_status' => $paymentResult->status,
            ]);
        } catch (NotFoundException $e) {
            // Recurso não existe no MP ou assinatura não vinculada — não é
            // falha de integração, não re-tentamos.
            $event->update([
                'status' => WebhookEvent::STATUS_PROCESSED,
                'last_error' => $e->getMessage(),
                'processed_at' => now(),
            ]);
            Log::info('Webhook MP: recurso/assinatura não encontrado (aceito)', [
                'webhook_event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $event->update([
                'status' => WebhookEvent::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ]);
            Log::error('Webhook MP: falha ao processar', [
                'webhook_event_id' => $event->id,
                'attempts' => $event->attempts,
                'error' => $e->getMessage(),
            ]);
            // Re-lançar para o Laravel re-enfileirar (se attempts < tries).
            throw $e;
        }
    }
}
