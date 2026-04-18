<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'autorizacoes_fornecimento';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('autorizacoes_fornecimento', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->onDelete('set null');
            $table->string('numero', Blueprint::VARCHAR_DEFAULT);
            $table->date('data');
            $table->date('data_adjudicacao')->nullable();
            $table->date('data_homologacao')->nullable();
            $table->date('data_fim_vigencia')->nullable();
            $table->boolean('vigente')->default(true);
            $table->decimal('valor', 15, 2);
            $table->text('condicoes_af')->nullable();
            $table->text('itens_arrematados')->nullable();
            $table->decimal('saldo', 15, 2);
            $table->decimal('valor_empenhado', 15, 2)->default(0);
            $table->status([
                'aguardando_empenho',
                'atendendo',
                'concluida'
            ], 'aguardando_empenho', 'situacao');
            $table->status([
                'aguardando_empenho',
                'atendendo_empenho',
                'concluida',
                'parcialmente_atendida'
            ], null, 'situacao_detalhada')->nullable();
            $table->string('numero_cte', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->observacao('observacoes');
            $table->datetimesWithSoftDeletes();
            
            // ⚡ Índices para performance
            $table->index('situacao');
            $table->index('data');
            $table->index('data_fim_vigencia');
            $table->index('vigente');
            $table->index('processo_id');
            $table->index(['empresa_id', 'situacao']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('autorizacoes_fornecimento');
    }
};


