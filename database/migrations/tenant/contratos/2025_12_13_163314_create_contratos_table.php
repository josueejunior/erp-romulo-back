<?php

use Illuminate\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->string('numero', Blueprint::VARCHAR_DEFAULT);
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->decimal('valor_total', 15, 2);
            $table->text('condicoes_comerciais')->nullable();
            $table->text('condicoes_tecnicas')->nullable();
            $table->text('locais_entrega')->nullable();
            $table->text('prazos_contrato')->nullable();
            $table->text('regras_contrato')->nullable();
            $table->decimal('saldo', 15, 2);
            $table->decimal('valor_empenhado', 15, 2)->default(0);
            $table->status(['vigente', 'encerrado', 'cancelado'], 'vigente', 'situacao');
            $table->date('data_assinatura')->nullable();
            $table->boolean('vigente')->default(true);
            $table->string('arquivo_contrato', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('numero_cte', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->observacao('observacoes');
            $table->datetimesWithSoftDeletes();
            
            // ⚡ Índices para performance
            $table->index('situacao');
            $table->index('data_inicio');
            $table->index('data_fim');
            $table->index('vigente');
            $table->index('processo_id');
            $table->index(['empresa_id', 'vigente']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};


