<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'cupons';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cupons', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->enum('tipo', ['percentual', 'valor_fixo'])->default('percentual');
            $table->decimal('valor', 10, 2); // Se percentual: 10.00 = 10%, se valor_fixo: valor em reais
            $table->date('data_validade_inicio')->nullable();
            $table->date('data_validade_fim')->nullable();
            $table->integer('limite_uso')->nullable(); // null = ilimitado
            $table->integer('total_usado')->default(0);
            $table->boolean('uso_unico_por_usuario')->default(true); // Cada usuário pode usar apenas 1x
            $table->json('planos_permitidos')->nullable(); // Array de IDs de planos (null = todos)
            $table->decimal('valor_minimo_compra', 10, 2)->nullable(); // Valor mínimo para usar cupom
            $table->ativo();
            $table->observacao('descricao');
            $table->datetimes();

            // ⚡ Índices para performance
            // codigo já tem índice único (->unique())
            $table->index('ativo');
            $table->index(['data_validade_inicio', 'data_validade_fim']);
        });

        // Tabela para rastrear uso de cupons
        Schema::create('cupons_uso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cupom_id')->constrained('cupons')->onDelete('cascade');
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            // 🔥 IMPORTANTE: assinatura_id sem foreign key porque a tabela assinaturas está no banco do tenant
            $table->unsignedBigInteger('assinatura_id')->nullable();
            $table->decimal('valor_desconto_aplicado', 10, 2);
            $table->decimal('valor_original', 10, 2);
            $table->decimal('valor_final', 10, 2);
            $table->timestamp('usado_em');
            $table->datetimes();

            // ⚡ Índices para performance
            $table->index('tenant_id');
            $table->index('cupom_id');
            $table->index('assinatura_id'); // Índice mesmo sem foreign key
            $table->index('usado_em');
            $table->index(['cupom_id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cupons_uso');
        Schema::dropIfExists('cupons');
    }
};

