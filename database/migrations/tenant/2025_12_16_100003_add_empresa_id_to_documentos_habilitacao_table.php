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
        Schema::table('documentos_habilitacao', function (Blueprint $table) {
            // Como cada tenant tem seu próprio banco, não precisamos de empresa_id
            // Os documentos já pertencem ao tenant
            // Mas vamos adicionar um campo para identificar se está disponível para uso
            $table->boolean('ativo')->default(true)->after('observacoes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos_habilitacao', function (Blueprint $table) {
            $table->dropColumn('ativo');
        });
    }
};

