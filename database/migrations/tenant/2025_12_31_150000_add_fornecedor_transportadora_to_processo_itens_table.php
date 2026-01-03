<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'processo_itens';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('processo_itens', function (Blueprint $table) {
            // Adicionar campos para fornecedor e transportadora (opcional, para facilitar seleção)
            if (!Schema::hasColumn('processo_itens', 'fornecedor_id')) {
                $table->foreignId('fornecedor_id')
                    ->nullable()
                    ->after('processo_id')
                    ->constrained('fornecedores')
                    ->onDelete('set null');
            }
            
            if (!Schema::hasColumn('processo_itens', 'transportadora_id')) {
                $table->foreignId('transportadora_id')
                    ->nullable()
                    ->after('fornecedor_id')
                    ->constrained('fornecedores')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processo_itens', function (Blueprint $table) {
            if (Schema::hasColumn('processo_itens', 'transportadora_id')) {
                $table->dropForeign(['transportadora_id']);
                $table->dropColumn('transportadora_id');
            }
            
            if (Schema::hasColumn('processo_itens', 'fornecedor_id')) {
                $table->dropForeign(['fornecedor_id']);
                $table->dropColumn('fornecedor_id');
            }
        });
    }
};


