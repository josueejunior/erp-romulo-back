<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'processo_item_vinculos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar se processo_itens existe (obrigatório)
        $this->checkTablesExist('processo_itens');

        // Criar tabela sem foreign keys opcionais primeiro
        Schema::create('processo_item_vinculos', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('processo_item_id')->constrained('processo_itens')->onDelete('cascade');
            
            // Colunas sem foreign keys (serão adicionadas depois quando as tabelas existirem)
            $table->unsignedBigInteger('contrato_id')->nullable();
            $table->unsignedBigInteger('autorizacao_fornecimento_id')->nullable();
            $table->unsignedBigInteger('empenho_id')->nullable();
            
            $table->decimal('quantidade', 15, 2)->default(0);
            $table->decimal('valor_unitario', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->observacao('observacoes');
            $table->datetimes();

            // ⚡ Índices para performance
            $table->index('processo_item_id');
            $table->index('contrato_id');
            $table->index('autorizacao_fornecimento_id');
            $table->index('empenho_id');
        });
        
        // Adicionar foreign keys de forma segura usando o helper
        $this->addSafeForeignKeys('processo_item_vinculos', [
            ['column' => 'contrato_id', 'table' => 'contratos', 'nullable' => true],
            ['column' => 'autorizacao_fornecimento_id', 'table' => 'autorizacoes_fornecimento', 'nullable' => true],
            ['column' => 'empenho_id', 'table' => 'empenhos', 'nullable' => true],
        ]);
        
        // Adicionar constraint unique depois que todas as foreign keys estiverem criadas
        Schema::table('processo_item_vinculos', function (Blueprint $table) {
            $table->unique(['processo_item_id', 'contrato_id', 'autorizacao_fornecimento_id', 'empenho_id'], 'unique_vinculo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processo_item_vinculos');
    }
};


