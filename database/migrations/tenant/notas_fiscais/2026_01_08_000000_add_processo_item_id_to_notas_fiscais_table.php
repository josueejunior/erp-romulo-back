<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'notas_fiscais';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->foreignId('processo_item_id')
                ->nullable()
                ->after('processo_id')
                ->constrained('processo_itens')
                ->onDelete('set null');
            
            // Índice para performance em consultas por item
            $table->index('processo_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropForeign(['processo_item_id']);
            $table->dropIndex(['processo_item_id']);
            $table->dropColumn('processo_item_id');
        });
    }
};

