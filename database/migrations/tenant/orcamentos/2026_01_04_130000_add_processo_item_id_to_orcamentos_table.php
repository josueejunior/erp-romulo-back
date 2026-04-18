<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'orcamentos';

    /**
     * Run the migrations.
     * Adiciona processo_item_id se não existir
     */
    public function up(): void
    {
        Schema::table('orcamentos', function (Blueprint $table) {
            // Adiciona processo_item_id se não existir
            if (!Schema::hasColumn('orcamentos', 'processo_item_id')) {
                $table->foreignId('processo_item_id')
                    ->nullable()
                    ->after('processo_id')
                    ->constrained('processo_itens')
                    ->onDelete('cascade');
                
                // ⚡ Índice para performance
                $table->index('processo_item_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orcamentos', function (Blueprint $table) {
            if (Schema::hasColumn('orcamentos', 'processo_item_id')) {
                $table->dropForeign(['processo_item_id']);
                $table->dropColumn('processo_item_id');
            }
        });
    }
};


