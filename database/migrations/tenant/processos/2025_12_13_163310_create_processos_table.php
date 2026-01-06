<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'processos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('processos', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('orgao_id')->constrained('orgaos')->onDelete('restrict');
            $table->foreignId('setor_id')->nullable()->constrained('setors')->onDelete('restrict');
            $table->enum('modalidade', ['dispensa', 'pregao']);
            $table->string('numero_modalidade', Blueprint::VARCHAR_DEFAULT);
            $table->string('numero_processo_administrativo', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('link_edital', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('portal', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('numero_edital', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->boolean('srp')->default(false);
            $table->descricao('objeto_resumido');
            $table->dateTime('data_hora_sessao_publica');
            $table->time('horario_sessao_publica')->nullable();
            $table->string('endereco_entrega', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->text('local_entrega_detalhado')->nullable();
            $table->string('forma_entrega', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('prazo_entrega', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->observacao('forma_prazo_entrega');
            $table->text('prazos_detalhados')->nullable();
            $table->observacao('prazo_pagamento');
            $table->observacao('validade_proposta');
            $table->date('validade_proposta_inicio')->nullable();
            $table->date('validade_proposta_fim')->nullable();
            $table->string('tipo_selecao_fornecedor', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('tipo_disputa', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->enum('status', [
                'participacao',
                'julgamento_habilitacao',
                'vencido',
                'perdido',
                'execucao',
                'pagamento',
                'encerramento',
                'arquivado'
            ])->default('participacao');
            $table->enum('status_participacao', [
                'normal',
                'adiado',
                'suspenso',
                'cancelado'
            ])->nullable();
            $table->enum('status_pagamento', ['pendente', 'parcial', 'pago'])->default('pendente');
            $table->enum('status_encerramento', ['aberto', 'parcial', 'encerrado'])->default('aberto');
            $table->date('data_recebimento_pagamento')->nullable();
            $table->dateTime('data_arquivamento')->nullable();
            $table->observacao('observacoes');
            $table->datetimesWithSoftDeletes();
            
            // ⚡ Índices para performance
            $table->index('status');
            $table->index('data_hora_sessao_publica');
            $table->index('status_participacao');
            $table->index(['empresa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processos');
    }
};

