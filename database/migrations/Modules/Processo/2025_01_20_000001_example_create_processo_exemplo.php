<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exemplo de migration usando a arquitetura customizada
 * Este é apenas um exemplo - não executar em produção
 */
return new class extends Migration
{
    public string $table = 'processo_exemplo';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('processo_exemplo', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys usando métodos auxiliares
            $table->foreignEmpresa();
            $table->foreignIdCustom('orgao_id', 'orgaos', true, 'cascade');
            $table->foreignUsuario(true);
            
            // Campos usando constantes padronizadas
            $table->string('numero_modalidade', Blueprint::VARCHAR_DEFAULT);
            $table->descricao('objeto_resumido');
            $table->observacao();
            
            // Campos auxiliares
            $table->email('email_contato', true);
            $table->telefone('telefone_contato', true);
            $table->endereco();
            $table->coordenadas();
            
            // Status e ativo
            $table->status(['rascunho', 'publicado', 'encerrado'], 'rascunho');
            $table->ativo();
            
            // Timestamps em português
            $table->datetimes();
            
            // Índices
            $table->index('numero_modalidade');
            $table->index(['empresa_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processo_exemplo');
    }
};

