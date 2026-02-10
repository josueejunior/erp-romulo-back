<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Adicionar recurso 'calendarios' ao plano Gratuito
        $plano = DB::table('planos')->where('nome', 'Gratuito')->first();
        
        if ($plano) {
            $recursos = json_decode($plano->recursos_disponiveis, true) ?? [];
            
            // Adicionar 'calendarios' se ainda não existir
            if (!in_array('calendarios', $recursos)) {
                $recursos[] = 'calendarios';
                DB::table('planos')
                    ->where('nome', 'Gratuito')
                    ->update(['recursos_disponiveis' => json_encode($recursos)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover recurso 'calendarios' do plano Gratuito
        $plano = DB::table('planos')->where('nome', 'Gratuito')->first();
        
        if ($plano) {
            $recursos = json_decode($plano->recursos_disponiveis, true) ?? [];
            
            // Remover 'calendarios' se existir
            $recursos = array_filter($recursos, fn($r) => $r !== 'calendarios');
            $recursos = array_values($recursos); // Reindexar array
            
            DB::table('planos')
                ->where('nome', 'Gratuito')
                ->update(['recursos_disponiveis' => json_encode($recursos)]);
        }
    }
};
