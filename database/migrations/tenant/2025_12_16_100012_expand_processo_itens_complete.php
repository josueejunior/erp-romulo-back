<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('processo_itens', function (Blueprint $table) {
            // Identificação
            $table->string('codigo_interno')->nullable()->after('numero_item');
            
            // Dados técnicos do edital
            $table->text('observacoes_edital')->nullable()->after('especificacao_tecnica');
            
            // Dados estimados
            $table->decimal('valor_estimado_total', 15, 2)->nullable()->after('valor_estimado');
            $table->string('fonte_valor')->nullable()->after('valor_estimado_total'); // 'edital' ou 'pesquisa'
            
            // Formação de preços
            $table->decimal('valor_minimo_venda', 15, 2)->nullable()->after('valor_negociado');
            
            // Dados pós-disputa
            $table->date('data_disputa')->nullable()->after('valor_final_sessao');
            
            // Situação final do item
            $table->enum('situacao_final', ['vencido', 'perdido'])->nullable()->after('status_item');
            
            // Campos financeiros (calculados)
            $table->decimal('valor_vencido', 15, 2)->default(0)->after('situacao_final');
            $table->decimal('valor_empenhado', 15, 2)->default(0)->after('valor_vencido');
            $table->decimal('valor_faturado', 15, 2)->default(0)->after('valor_empenhado');
            $table->decimal('valor_pago', 15, 2)->default(0)->after('valor_faturado');
            $table->decimal('saldo_aberto', 15, 2)->default(0)->after('valor_pago');
            $table->decimal('lucro_bruto', 15, 2)->default(0)->after('saldo_aberto');
            $table->decimal('lucro_liquido', 15, 2)->default(0)->after('lucro_bruto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processo_itens', function (Blueprint $table) {
            $table->dropColumn([
                'codigo_interno',
                'observacoes_edital',
                'valor_estimado_total',
                'fonte_valor',
                'valor_minimo_venda',
                'data_disputa',
                'situacao_final',
                'valor_vencido',
                'valor_empenhado',
                'valor_faturado',
                'valor_pago',
                'saldo_aberto',
                'lucro_bruto',
                'lucro_liquido',
            ]);
        });
    }
};




