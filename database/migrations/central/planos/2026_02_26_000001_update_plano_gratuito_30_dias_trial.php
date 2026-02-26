<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Atualiza o plano Gratuito para trial de 30 dias (limite_dias = 30).
 * Planos já existentes passam a usar 30 dias em vez do padrão antigo (3 dias).
 */
return new class extends Migration
{
    protected string $table = 'planos';

    public function up(): void
    {
        DB::table($this->table)
            ->where('preco_mensal', 0)
            ->update([
                'limite_dias' => 30,
                'descricao' => 'Plano gratuito de teste de 30 dias. Ideal para conhecer o sistema antes de contratar um plano pago.',
                'atualizado_em' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table($this->table)
            ->where('preco_mensal', 0)
            ->update([
                'limite_dias' => null,
                'descricao' => 'Plano gratuito de teste de 3 dias. Ideal para conhecer o sistema antes de contratar um plano pago.',
                'atualizado_em' => now(),
            ]);
    }
};
