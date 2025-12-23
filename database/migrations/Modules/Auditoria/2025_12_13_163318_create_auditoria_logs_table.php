<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'auditoria_logs';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auditoria_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUsuario(true); // usuario_id nullable
            $table->string('model_type', Blueprint::VARCHAR_DEFAULT);
            $table->unsignedBigInteger('model_id');
            $table->string('acao', Blueprint::VARCHAR_SMALL); // created, updated, deleted, status_changed
            $table->string('campo', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->text('valor_anterior')->nullable();
            $table->text('valor_novo')->nullable();
            $table->observacao('observacoes');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->datetimes();
            
            $table->index(['model_type', 'model_id']);
            $table->index('usuario_id');
            $table->index('acao');
            $table->index(Blueprint::CREATED_AT);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditoria_logs');
    }
};
