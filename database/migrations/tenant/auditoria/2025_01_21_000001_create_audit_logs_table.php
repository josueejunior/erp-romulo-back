<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'audit_logs';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUsuario(true);
            // tenant_id: referência lógica (tabela tenants fica só no banco central)
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('action', Blueprint::VARCHAR_TINY); // created, updated, deleted, status_changed, payment_processed, etc
            $table->string('model_type', Blueprint::VARCHAR_DEFAULT); // App\Models\Processo
            $table->unsignedBigInteger('model_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->observacao('description');
            $table->datetimes();

            // ⚡ Índices para melhor performance
            $table->index(['model_type', 'model_id']);
            $table->index('usuario_id');
            $table->index('tenant_id');
            $table->index('action');
            $table->index(Blueprint::CREATED_AT);
            $table->index(['tenant_id', 'action']); // Para buscar operações críticas por tenant
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};


