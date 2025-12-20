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
        // Verificar se a coluna já existe antes de adicionar
        if (!Schema::hasColumn('documentos_habilitacao', 'empresa_id')) {
            Schema::table('documentos_habilitacao', function (Blueprint $table) {
                $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Verificar se a coluna existe antes de remover
        if (Schema::hasColumn('documentos_habilitacao', 'empresa_id')) {
            Schema::table('documentos_habilitacao', function (Blueprint $table) {
                try {
                    $table->dropForeign(['empresa_id']);
                } catch (\Exception $e) {
                    // Ignorar se não houver foreign key
                }
                $table->dropColumn('empresa_id');
            });
        }
    }
};

