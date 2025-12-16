<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Alterar o enum de status para incluir pagamento e encerramento
        DB::statement("ALTER TABLE processos MODIFY COLUMN status ENUM(
            'participacao',
            'julgamento_habilitacao',
            'vencido',
            'perdido',
            'execucao',
            'pagamento',
            'encerramento',
            'arquivado'
        ) DEFAULT 'participacao'");

        // Adicionar campo para status em participação
        Schema::table('processos', function (Blueprint $table) {
            $table->enum('status_participacao', [
                'normal',
                'adiado',
                'suspenso',
                'cancelado'
            ])->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover campo status_participacao
        Schema::table('processos', function (Blueprint $table) {
            $table->dropColumn('status_participacao');
        });

        // Reverter enum para valores originais
        DB::statement("ALTER TABLE processos MODIFY COLUMN status ENUM(
            'participacao',
            'julgamento_habilitacao',
            'vencido',
            'perdido',
            'execucao',
            'arquivado'
        ) DEFAULT 'participacao'");
    }
};

