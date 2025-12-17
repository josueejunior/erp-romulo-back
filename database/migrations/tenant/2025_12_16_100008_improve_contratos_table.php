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
        Schema::table('contratos', function (Blueprint $table) {
            // Campos adicionais do contrato
            $table->text('condicoes_comerciais')->nullable()->after('valor_total');
            $table->text('condicoes_tecnicas')->nullable()->after('condicoes_comerciais');
            $table->text('locais_entrega')->nullable()->after('condicoes_tecnicas');
            $table->text('prazos_contrato')->nullable()->after('locais_entrega');
            $table->text('regras_contrato')->nullable()->after('prazos_contrato');
            
            // Controle de vigência
            $table->date('data_assinatura')->nullable()->after('data_fim');
            $table->boolean('vigente')->default(true)->after('data_assinatura');
            
            // Atualização automática de saldo
            $table->decimal('valor_empenhado', 15, 2)->default(0)->after('saldo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn([
                'condicoes_comerciais',
                'condicoes_tecnicas',
                'locais_entrega',
                'prazos_contrato',
                'regras_contrato',
                'data_assinatura',
                'vigente',
                'valor_empenhado'
            ]);
        });
    }
};




