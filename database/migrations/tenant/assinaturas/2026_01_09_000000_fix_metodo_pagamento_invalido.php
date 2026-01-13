<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Corrige valores inválidos de metodo_pagamento no banco de dados.
     * Converte valores antigos/inválidos para valores válidos.
     */
    public function up(): void
    {
        // Mapeamento de valores inválidos para valores válidos
        $mapeamento = [
            'master' => 'gratuito', // 'master' era usado antigamente para planos gratuitos
            'card' => 'credit_card',
            'creditcard' => 'credit_card',
            'debit_card' => 'credit_card',
        ];

        foreach ($mapeamento as $valorInvalido => $valorValido) {
            $afetados = DB::table('assinaturas')
                ->where('metodo_pagamento', $valorInvalido)
                ->update(['metodo_pagamento' => $valorValido]);

            if ($afetados > 0) {
                Log::info('Migration: metodo_pagamento corrigido', [
                    'valor_antigo' => $valorInvalido,
                    'valor_novo' => $valorValido,
                    'registros_afetados' => $afetados,
                ]);
            }
        }

        // Corrigir valores que não estão na lista de válidos
        // Usar 'pendente' como fallback para valores desconhecidos
        $metodosValidos = ['gratuito', 'credit_card', 'pix', 'boleto', 'pendente'];
        
        $valoresInvalidos = DB::table('assinaturas')
            ->whereNotNull('metodo_pagamento')
            ->whereNotIn('metodo_pagamento', $metodosValidos)
            ->pluck('metodo_pagamento')
            ->unique();

        foreach ($valoresInvalidos as $valorInvalido) {
            $afetados = DB::table('assinaturas')
                ->where('metodo_pagamento', $valorInvalido)
                ->update(['metodo_pagamento' => 'pendente']);

            if ($afetados > 0) {
                Log::warning('Migration: metodo_pagamento desconhecido corrigido para pendente', [
                    'valor_antigo' => $valorInvalido,
                    'registros_afetados' => $afetados,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Não há como reverter com precisão, pois não sabemos qual era o valor original
     * de cada registro. Esta migration é idempotente e pode ser executada múltiplas vezes.
     */
    public function down(): void
    {
        // Não há como reverter com precisão
        // Esta migration é idempotente
    }
};






