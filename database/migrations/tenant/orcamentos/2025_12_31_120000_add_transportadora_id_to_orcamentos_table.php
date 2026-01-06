<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'orcamentos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orcamentos', function (Blueprint $table) {
            // Verificar se a coluna já existe antes de adicionar
            if (!Schema::hasColumn('orcamentos', 'transportadora_id')) {
                $table->foreignId('transportadora_id')
                    ->nullable()
                    ->after('fornecedor_id')
                    ->constrained('fornecedores')
                    ->onDelete('set null'); // Transportadora é um fornecedor
                
                // ⚡ Índice para performance
                $table->index('transportadora_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orcamentos', function (Blueprint $table) {
            if (Schema::hasColumn('orcamentos', 'transportadora_id')) {
                $table->dropForeign(['transportadora_id']);
                $table->dropColumn('transportadora_id');
            }
        });
    }
};

