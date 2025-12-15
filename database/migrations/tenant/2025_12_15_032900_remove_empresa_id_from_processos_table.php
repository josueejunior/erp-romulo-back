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
        Schema::table('processos', function (Blueprint $table) {
            // Verificar se a coluna existe antes de tentar removê-la
            if (Schema::hasColumn('processos', 'empresa_id')) {
                $table->dropForeign(['empresa_id']);
                $table->dropColumn('empresa_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->onDelete('restrict');
        });
    }
};
