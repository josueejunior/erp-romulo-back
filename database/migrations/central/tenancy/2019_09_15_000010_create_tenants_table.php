<?php

declare(strict_types=1);

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'tenants';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id(); // Usa bigInteger auto-increment

            // Colunas customizadas do tenant
            $table->string('razao_social', Blueprint::VARCHAR_DEFAULT);
            $table->string('cnpj', 18)->nullable()->unique();
            $table->string('email', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('status', 20)->default('ativa');
            
            // Endereço
            $table->string('endereco', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('cidade', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('estado', 2)->nullable();
            $table->string('cep', 10)->nullable();
            
            // Contatos
            $table->json('telefones')->nullable();
            $table->json('emails_adicionais')->nullable();
            
            // Dados bancários
            $table->string('banco', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('agencia', 20)->nullable();
            $table->string('conta', 20)->nullable();
            $table->string('tipo_conta', 20)->nullable(); // corrente, poupanca
            $table->string('pix', Blueprint::VARCHAR_DEFAULT)->nullable();
            
            // Representante legal
            $table->string('representante_legal_nome', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('representante_legal_cpf', 14)->nullable();
            $table->string('representante_legal_cargo', Blueprint::VARCHAR_DEFAULT)->nullable();
            
            // Logo e configurações
            $table->string('logo', Blueprint::VARCHAR_DEFAULT)->nullable();
            
            // Relacionamentos
            // plano_atual_id sem foreign key (planos será criado depois)
            $table->unsignedBigInteger('plano_atual_id')->nullable();
            $table->unsignedBigInteger('assinatura_atual_id')->nullable();
            
            // Limites
            $table->integer('limite_processos')->nullable();
            $table->integer('limite_usuarios')->nullable();

            $table->datetimes();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

