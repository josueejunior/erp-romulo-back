<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'planos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            // Percentual de comissão do afiliado por plano (40%, 60%, 100%)
            // A comissão final será: 30% × percentual_comissao_afiliado × valor_do_plano
            // Exemplo: Plano Básico (40%) = 30% × 40% = 12% do valor do plano
            $table->decimal('percentual_comissao_afiliado', 5, 2)
                ->default(100.00) // 100% por padrão (plano Premium/Avançado)
                ->after('preco_anual')
                ->comment('Percentual de comissão do afiliado por plano (40%, 60%, 100%). Comissão final = 30% × este percentual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('planos', function (Blueprint $table) {
            $table->dropColumn('percentual_comissao_afiliado');
        });
    }
};

