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
        Schema::table('autorizacoes_fornecimento', function (Blueprint $table) {
            // Campos adicionais da AF
            $table->text('condicoes_af')->nullable()->after('valor');
            $table->text('itens_arrematados')->nullable()->after('condicoes_af');
            
            // Controle de vigência
            $table->date('data_adjudicacao')->nullable()->after('data');
            $table->date('data_homologacao')->nullable()->after('data_adjudicacao');
            $table->date('data_fim_vigencia')->nullable()->after('data_homologacao');
            $table->boolean('vigente')->default(true)->after('data_fim_vigencia');
            
            // Situação da AF (atualização automática)
            $table->enum('situacao_detalhada', [
                'aguardando_empenho',
                'atendendo_empenho',
                'concluida',
                'parcialmente_atendida'
            ])->nullable()->after('situacao');
            
            // Valor empenhado
            $table->decimal('valor_empenhado', 15, 2)->default(0)->after('saldo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('autorizacoes_fornecimento', function (Blueprint $table) {
            $table->dropColumn([
                'condicoes_af',
                'itens_arrematados',
                'data_adjudicacao',
                'data_homologacao',
                'data_fim_vigencia',
                'vigente',
                'situacao_detalhada',
                'valor_empenhado'
            ]);
        });
    }
};




