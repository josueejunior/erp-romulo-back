<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'users';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function ($table) {
            /** @var \Illuminate\Database\Schema\Blueprint $table */
            $table->id();
            // Criar coluna sem foreign key primeiro (empresas ainda não existe)
            $table->unsignedBigInteger('empresa_ativa_id')->nullable();
            $table->string('name', Blueprint::VARCHAR_DEFAULT);
            $table->email()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->rememberToken();
            // Usar método direto para timestamps customizados
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });
        
        // Adicionar foreign key depois que a tabela empresas for criada
        // Isso será feito após a migration de empresas executar
        if (Schema::hasTable('empresas')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('empresa_ativa_id')
                    ->references('id')
                    ->on('empresas')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

