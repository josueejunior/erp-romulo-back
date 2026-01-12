<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela CENTRAL de indicações de afiliados
     * 
     * Registra todas as empresas indicadas por afiliados,
     * com histórico de status e valores para cálculo de comissões.
     */
    public function up(): void
    {
        Schema::create('afiliado_indicacoes', function (Blueprint $table) {
            $table->id();
            
            // Relacionamentos
            $table->foreignId('afiliado_id')->constrained('afiliados')->onDelete('cascade');
            $table->unsignedBigInteger('tenant_id')->comment('ID do tenant da empresa');
            $table->unsignedBigInteger('empresa_id')->comment('ID da empresa no tenant');
            
            // Dados da indicação (congelados no momento da adesão)
            $table->string('codigo_usado', 50)->comment('Código do afiliado usado');
            $table->decimal('desconto_aplicado', 5, 2)->comment('% desconto aplicado');
            $table->decimal('comissao_percentual', 5, 2)->comment('% comissão do afiliado');
            
            // Dados do plano contratado
            $table->unsignedBigInteger('plano_id')->nullable();
            $table->string('plano_nome', 100)->nullable();
            $table->decimal('valor_plano_original', 10, 2)->nullable()->comment('Valor original do plano');
            $table->decimal('valor_plano_com_desconto', 10, 2)->nullable()->comment('Valor após desconto');
            $table->decimal('valor_comissao', 10, 2)->nullable()->comment('Valor da comissão');
            
            // Status da empresa/assinatura
            $table->enum('status', [
                'ativa',           // Empresa em dia
                'inadimplente',    // Pagamento atrasado
                'cancelada',       // Assinatura cancelada
                'trial',           // Em período de teste
            ])->default('trial');
            
            // Datas importantes
            $table->timestamp('indicado_em')->useCurrent()->comment('Data da indicação');
            $table->timestamp('primeira_assinatura_em')->nullable()->comment('Data da primeira assinatura paga');
            $table->timestamp('cancelado_em')->nullable();
            
            // Controle de pagamento de comissão
            $table->boolean('comissao_paga')->default(false);
            $table->timestamp('comissao_paga_em')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('afiliado_id');
            $table->index('tenant_id');
            $table->index('empresa_id');
            $table->index('status');
            $table->index('indicado_em');
            $table->index(['afiliado_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afiliado_indicacoes');
    }
};





