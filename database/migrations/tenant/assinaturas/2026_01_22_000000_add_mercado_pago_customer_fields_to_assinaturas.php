<?php

declare(strict_types=1);

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'assinaturas';

    /**
     * Adiciona campos para suporte a Customer/Card ID do Mercado Pago
     * 
     * 🔥 MELHORIA: External Vaulting - Salvar apenas customer_id e card_id
     * (não são dados sensíveis, mas permitem cobrança futura sem reinserir cartão)
     */
    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            // Customer ID do Mercado Pago (permite cobrança futura)
            $table->string('mercado_pago_customer_id', Blueprint::VARCHAR_DEFAULT)->nullable()->after('transacao_id')
                ->comment('ID do Customer no Mercado Pago (para cobrança recorrente)');
            
            // Card ID do Mercado Pago (cartão salvo no vault do MP)
            $table->string('mercado_pago_card_id', Blueprint::VARCHAR_DEFAULT)->nullable()->after('mercado_pago_customer_id')
                ->comment('ID do Cartão salvo no Mercado Pago (para cobrança recorrente)');
            
            // Subscription ID do Mercado Pago (para assinaturas recorrentes nativas)
            $table->string('mercado_pago_subscription_id', Blueprint::VARCHAR_DEFAULT)->nullable()->after('mercado_pago_card_id')
                ->comment('ID da Subscription no Mercado Pago (para cobrança automática)');
            
            // Última tentativa de cobrança automática (para evitar tentativas excessivas)
            $table->timestamp('ultima_tentativa_cobranca')->nullable()->after('mercado_pago_subscription_id')
                ->comment('Data/hora da última tentativa de cobrança automática');
            
            // Contador de tentativas de cobrança (para retry inteligente)
            $table->integer('tentativas_cobranca')->default(0)->after('ultima_tentativa_cobranca')
                ->comment('Número de tentativas de cobrança automática realizadas');
            
            // Índices para performance
            $table->index('mercado_pago_customer_id');
            $table->index('mercado_pago_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            $table->dropColumn([
                'mercado_pago_customer_id',
                'mercado_pago_card_id',
                'mercado_pago_subscription_id',
                'ultima_tentativa_cobranca',
                'tentativas_cobranca',
            ]);
        });
    }
};



