<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'processo_itens';

    /**
     * Adiciona a coluna 'nome' aos itens do processo.
     *
     * A coluna é usada no cadastro de itens (campo 'nome' do formulário),
     * mas não existia na tabela, gerando erro de coluna indefinida.
     */
    public function up(): void
    {
        // Idempotente: só adiciona a coluna se ainda não existir (evita erro em tenants antigos/migrados manualmente)
        if (! Schema::hasColumn('processo_itens', 'nome')) {
            Schema::table('processo_itens', function (Blueprint $table) {
                // Tornar nullable para não quebrar dados antigos; a aplicação já trata como obrigatório no request.
                $table->string('nome', Blueprint::VARCHAR_DEFAULT)->nullable()->after('numero_item');
            });
        }
    }

    /**
     * Reverte a adição da coluna 'nome'.
     */
    public function down(): void
    {
        Schema::table('processo_itens', function (Blueprint $table) {
            $table->dropColumn('nome');
        });
    }
};

