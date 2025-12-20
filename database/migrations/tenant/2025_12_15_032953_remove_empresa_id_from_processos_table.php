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
        // Adicionar empresa_id em todas as tabelas que precisam
        $tabelas = [
            'processos',
            'orgaos',
            'setors',
            'fornecedores',
            'transportadoras',
            'documentos_habilitacao',
            'custos_indiretos',
            'orcamentos',
            'contratos',
            'empenhos',
            'notas_fiscais',
            'autorizacoes_fornecimento'
        ];
        
        foreach ($tabelas as $tabela) {
            if (!Schema::hasColumn($tabela, 'empresa_id')) {
                Schema::table($tabela, function (Blueprint $table) {
                    $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->onDelete('restrict');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter: remover empresa_id de todas as tabelas
        $tabelas = [
            'processos',
            'orgaos',
            'setors',
            'fornecedores',
            'transportadoras',
            'documentos_habilitacao',
            'custos_indiretos',
            'orcamentos',
            'contratos',
            'empenhos',
            'notas_fiscais',
            'autorizacoes_fornecimento'
        ];
        
        foreach ($tabelas as $tabela) {
            if (Schema::hasColumn($tabela, 'empresa_id')) {
                Schema::table($tabela, function (Blueprint $table) {
                    try {
                        $table->dropForeign(['empresa_id']);
                    } catch (\Exception $e) {
                        // Ignorar se não houver foreign key
                    }
                    $table->dropColumn('empresa_id');
                });
            }
        }
    }
};
