<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Adicionar campos para múltiplos emails e telefones (JSON)
            $table->json('emails')->nullable()->after('email');
            $table->json('telefones')->nullable()->after('telefone');
            
            // Melhorar dados bancários
            $table->string('banco_codigo')->nullable()->after('banco_tipo');
            $table->string('banco_pix')->nullable()->after('banco_codigo');
            $table->text('dados_bancarios_observacoes')->nullable()->after('banco_pix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['emails', 'telefones', 'banco_codigo', 'banco_pix', 'dados_bancarios_observacoes']);
        });
    }
};

