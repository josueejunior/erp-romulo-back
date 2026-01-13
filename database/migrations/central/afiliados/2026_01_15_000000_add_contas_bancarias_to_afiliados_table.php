<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona suporte a múltiplas contas bancárias
     * 
     * Adiciona campo JSON para armazenar array de contas bancárias
     * Mantém compatibilidade com campos antigos (banco, agencia, conta, tipo_conta, pix)
     */
    public function up(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->json('contas_bancarias')->nullable()->after('pix')->comment('Array de contas bancárias em formato JSON');
        });
    }

    public function down(): void
    {
        Schema::table('afiliados', function (Blueprint $table) {
            $table->dropColumn('contas_bancarias');
        });
    }
};






