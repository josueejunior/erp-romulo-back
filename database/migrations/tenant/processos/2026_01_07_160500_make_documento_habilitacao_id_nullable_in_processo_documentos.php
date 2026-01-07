<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public string $table = 'processo_documentos';

    /**
     * Run the migrations.
     * 
     * Torna documento_habilitacao_id nullable para permitir documentos customizados
     */
    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            // Primeiro, remover a foreign key constraint
            $table->dropForeign(['documento_habilitacao_id']);
        });

        // Tornar a coluna nullable (PostgreSQL requer SQL direto)
        DB::statement('ALTER TABLE processo_documentos ALTER COLUMN documento_habilitacao_id DROP NOT NULL');

        // Recriar a foreign key (nullable por padrão quando a coluna é nullable)
        Schema::table($this->table, function (Blueprint $table) {
            $table->foreign('documento_habilitacao_id')
                ->references('id')
                ->on('documentos_habilitacao')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            // Remover foreign key
            $table->dropForeign(['documento_habilitacao_id']);
        });

        // Tornar NOT NULL novamente
        DB::statement('ALTER TABLE processo_documentos ALTER COLUMN documento_habilitacao_id SET NOT NULL');

        // Recriar foreign key como NOT NULL
        Schema::table($this->table, function (Blueprint $table) {
            $table->foreign('documento_habilitacao_id')
                ->references('id')
                ->on('documentos_habilitacao')
                ->onDelete('cascade');
        });
    }
};

