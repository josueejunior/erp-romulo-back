<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Garante que todas as tabelas tenham empresa_id
     */
    public function up(): void
    {
        $tables = [
            'setors',
            'orgaos',
            'custo_indiretos',
            'fornecedores',
            'processos',
            'orcamentos',
            'contratos',
            'empenhos',
            'notas_fiscais',
            'autorizacoes_fornecimento',
            'documentos_habilitacao',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'empresa_id')) {
                Schema::table($table, function (Blueprint $tableSchema) {
                    $tableSchema->foreignId('empresa_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('empresas')
                        ->onDelete('cascade');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Não fazer rollback automático para evitar perda de dados
        // Se necessário, fazer rollback manualmente
    }
};
