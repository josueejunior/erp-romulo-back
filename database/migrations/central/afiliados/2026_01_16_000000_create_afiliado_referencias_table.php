<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('afiliado_referencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('afiliado_id')->constrained('afiliados')->onDelete('cascade');
            $table->string('referencia_code')->index(); // Código do afiliado usado na URL (?ref=code)
            $table->string('session_id')->nullable()->index(); // ID da sessão do navegador
            $table->string('ip_address', 45)->nullable(); // IPv4 ou IPv6
            $table->string('user_agent')->nullable();
            $table->string('email')->nullable()->index(); // Email do lead (quando disponível)
            $table->string('cnpj')->nullable()->index(); // CNPJ do lead (quando disponível)
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->onDelete('set null'); // Vinculado quando cadastro é concluído
            $table->boolean('cadastro_concluido')->default(false)->index();
            $table->boolean('cupom_aplicado')->default(false)->index(); // Se o cupom foi aplicado
            $table->timestamp('primeiro_acesso')->nullable();
            $table->timestamp('cadastro_concluido_em')->nullable();
            $table->json('metadata')->nullable(); // Dados adicionais (UTM, origem, etc)
            $table->timestamps();
            
            // Índices compostos para consultas frequentes
            $table->index(['afiliado_id', 'cadastro_concluido']);
            $table->index(['session_id', 'cadastro_concluido']);
            $table->index(['cnpj', 'cupom_aplicado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('afiliado_referencias');
    }
};




