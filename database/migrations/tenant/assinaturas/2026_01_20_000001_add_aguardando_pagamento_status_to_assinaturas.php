<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adiciona status 'aguardando_pagamento' à constraint de assinaturas
     * Permite criar assinaturas de planos pagos sem processar pagamento imediatamente
     */
    public function up(): void
    {
        // Remover constraint antiga
        DB::statement('ALTER TABLE assinaturas DROP CONSTRAINT IF EXISTS assinaturas_status_check');
        
        // Adicionar nova constraint com status 'aguardando_pagamento'
        DB::statement("
            ALTER TABLE assinaturas 
            ADD CONSTRAINT assinaturas_status_check 
            CHECK (status IN ('ativa', 'suspensa', 'expirada', 'cancelada', 'aguardando_pagamento'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remover constraint nova
        DB::statement('ALTER TABLE assinaturas DROP CONSTRAINT IF EXISTS assinaturas_status_check');
        
        // Restaurar constraint antiga (sem 'aguardando_pagamento')
        DB::statement("
            ALTER TABLE assinaturas 
            ADD CONSTRAINT assinaturas_status_check 
            CHECK (status IN ('ativa', 'suspensa', 'expirada', 'cancelada'))
        ");
    }
};





