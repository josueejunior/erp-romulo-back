<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('processo_itens', function (Blueprint $table) {
            // Adicionar campo 'nome' após 'numero_item' para facilitar identificação do produto
            if (!Schema::hasColumn('processo_itens', 'nome')) {
                $table->string('nome', Blueprint::VARCHAR_DEFAULT)->nullable()->after('numero_item');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processo_itens', function (Blueprint $table) {
            if (Schema::hasColumn('processo_itens', 'nome')) {
                $table->dropColumn('nome');
            }
        });
    }
};
