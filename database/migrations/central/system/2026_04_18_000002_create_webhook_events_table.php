<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela central de eventos de webhook recebidos (idempotência).
 *
 * Cada (provider, event_type, resource_id) é único. Ao chegar um webhook
 * repetido (mesmo payment_id), retornamos 200 sem reprocessar. O processamento
 * real acontece em job assíncrono (Laravel Queue) para que o endpoint responda
 * em <1s — exigência do MP (retry em 15min se não responder em 22s).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->default('mercadopago');
            $table->string('event_type', 64);
            $table->string('resource_id', 128);
            $table->string('action', 64)->nullable();
            $table->json('payload');
            $table->json('headers')->nullable();

            // Ciclo de vida do processamento
            $table->string('status', 32)->default('received'); // received, processing, processed, failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // Idempotência: (provider, event_type, resource_id) é único.
            $table->unique(['provider', 'event_type', 'resource_id'], 'webhook_events_unique_key');
            $table->index(['status', 'created_at']);
            $table->index('resource_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
