<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona campos de afiliado na tabela empresas
     * 
     * O percentual de desconto é "congelado" no momento da adesão,
     * garantindo que alterações futuras no afiliado não afetem clientes existentes.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // ID do afiliado (referência para tabela central)
            $table->unsignedBigInteger('afiliado_id')->nullable()->after('id');
            
            // Código do afiliado usado (para histórico)
            $table->string('afiliado_codigo', 50)->nullable()->comment('Código usado na adesão');
            
            // Percentual de desconto CONGELADO no momento da adesão
            $table->decimal('afiliado_desconto_aplicado', 5, 2)->nullable()->comment('% desconto aplicado na adesão');
            
            // Data em que o código foi aplicado
            $table->timestamp('afiliado_aplicado_em')->nullable()->comment('Data de aplicação do código');
            
            // Índices
            $table->index('afiliado_id');
            $table->index('afiliado_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropIndex(['afiliado_id']);
            $table->dropIndex(['afiliado_codigo']);
            $table->dropColumn([
                'afiliado_id',
                'afiliado_codigo',
                'afiliado_desconto_aplicado',
                'afiliado_aplicado_em',
            ]);
        });
    }
};







