<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona campo empresa_nome para exibição na UI
     * Evita necessidade de acessar tenant para buscar nome da empresa
     */
    public function up(): void
    {
        Schema::table('afiliado_indicacoes', function (Blueprint $table) {
            $table->string('empresa_nome', 255)->nullable()->after('empresa_id')
                ->comment('Nome da empresa indicada (razao_social) para exibição');
        });
    }

    public function down(): void
    {
        Schema::table('afiliado_indicacoes', function (Blueprint $table) {
            $table->dropColumn('empresa_nome');
        });
    }
};


