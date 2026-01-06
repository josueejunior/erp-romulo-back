<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'orcamentos';

    /**
     * Run the migrations.
     * Adiciona colunas que podem estar faltando na tabela orcamentos
     */
    public function up(): void
    {
        Schema::table('orcamentos', function (Blueprint $table) {
            // Adiciona custo_produto se não existir
            if (!Schema::hasColumn('orcamentos', 'custo_produto')) {
                $table->decimal('custo_produto', 15, 2)->nullable()->after('fornecedor_id');
            }
            
            // Adiciona marca_modelo se não existir
            if (!Schema::hasColumn('orcamentos', 'marca_modelo')) {
                $table->string('marca_modelo', 255)->nullable()->after('custo_produto');
            }
            
            // Adiciona ajustes_especificacao se não existir
            if (!Schema::hasColumn('orcamentos', 'ajustes_especificacao')) {
                $table->text('ajustes_especificacao')->nullable()->after('marca_modelo');
            }
            
            // Adiciona frete se não existir
            if (!Schema::hasColumn('orcamentos', 'frete')) {
                $table->decimal('frete', 15, 2)->default(0)->after('ajustes_especificacao');
            }
            
            // Adiciona frete_incluido se não existir
            if (!Schema::hasColumn('orcamentos', 'frete_incluido')) {
                $table->boolean('frete_incluido')->default(false)->after('frete');
            }
            
            // Adiciona fornecedor_escolhido se não existir
            if (!Schema::hasColumn('orcamentos', 'fornecedor_escolhido')) {
                $table->boolean('fornecedor_escolhido')->default(false)->after('frete_incluido');
            }
            
            // Adiciona observacoes se não existir
            if (!Schema::hasColumn('orcamentos', 'observacoes')) {
                $table->text('observacoes')->nullable()->after('fornecedor_escolhido');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orcamentos', function (Blueprint $table) {
            $columns = [
                'custo_produto', 'marca_modelo', 'ajustes_especificacao', 
                'frete', 'frete_incluido', 'fornecedor_escolhido', 
                'observacoes'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('orcamentos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

