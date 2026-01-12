<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela CENTRAL de Afiliados
     * 
     * Gerenciada pelo painel administrativo.
     * Cada afiliado tem um código único que funciona como cupom de desconto.
     */
    public function up(): void
    {
        Schema::create('afiliados', function (Blueprint $table) {
            $table->id();
            
            // Dados Cadastrais
            $table->string('nome', 255);
            $table->string('documento', 20)->unique()->comment('CPF ou CNPJ');
            $table->enum('tipo_documento', ['cpf', 'cnpj'])->default('cpf');
            
            // Dados de Contato
            $table->string('email', 255)->unique();
            $table->string('telefone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            
            // Endereço (opcional)
            $table->string('endereco', 255)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('cep', 10)->nullable();
            
            // Código/Token do Afiliado (cupom de desconto)
            $table->string('codigo', 50)->unique()->comment('Código único usado como cupom');
            
            // Configuração de Comissão/Desconto
            $table->decimal('percentual_desconto', 5, 2)->default(0)->comment('% de desconto para clientes');
            $table->decimal('percentual_comissao', 5, 2)->default(0)->comment('% de comissão para o afiliado');
            
            // Dados Bancários (para pagamento de comissões)
            $table->string('banco', 100)->nullable();
            $table->string('agencia', 20)->nullable();
            $table->string('conta', 30)->nullable();
            $table->enum('tipo_conta', ['corrente', 'poupanca'])->nullable();
            $table->string('pix', 255)->nullable()->comment('Chave PIX');
            
            // Status e Controle
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();
            
            // Timestamps padrão Laravel
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('codigo');
            $table->index('documento');
            $table->index('email');
            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afiliados');
    }
};






