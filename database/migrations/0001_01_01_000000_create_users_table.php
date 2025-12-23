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
            // Usar método direto do Laravel para criar foreign key
            $table->foreignId('empresa_ativa_id')
                ->nullable()
                ->constrained('empresas')
                ->onDelete('set null');
            $table->string('name', Blueprint::VARCHAR_DEFAULT);
            $table->email()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->rememberToken();
            // Usar método direto para timestamps customizados
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
