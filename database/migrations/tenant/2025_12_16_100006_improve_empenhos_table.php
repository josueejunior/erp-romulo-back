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
        Schema::table('empenhos', function (Blueprint $table) {
            // Data de recebimento do empenho (para controle de prazos)
            $table->date('data_recebimento')->nullable()->after('data');
            
            // Situação do empenho (para controle automático de prazos)
            $table->enum('situacao', [
                'aguardando_entrega',
                'em_atendimento',
                'atendido',
                'atrasado',
                'concluido'
            ])->default('aguardando_entrega')->after('concluido');
            
            // Prazo de entrega calculado (data_recebimento + prazo do contrato/AF)
            $table->date('prazo_entrega_calculado')->nullable()->after('data_recebimento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empenhos', function (Blueprint $table) {
            $table->dropColumn(['data_recebimento', 'situacao', 'prazo_entrega_calculado']);
        });
    }
};

