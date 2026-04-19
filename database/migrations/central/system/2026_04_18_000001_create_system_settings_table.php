<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela genérica de configurações globais do sistema (fora do tenant).
 *
 * Usada para credenciais de integrações (Mercado Pago, etc.) gerenciadas
 * via painel admin — evita editar .env em produção e permite rotação rápida.
 *
 * `value` é armazenado já criptografado (AES via Laravel Crypt). O Model
 * expõe acessores que cuidam da criptografia automaticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 128)->unique();
            $table->text('value')->nullable();      // já criptografado
            $table->string('group', 64)->nullable(); // ex.: "mercadopago", "notificacoes"
            $table->boolean('is_secret')->default(true); // se true, nunca retorna em cleartext ao front
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
