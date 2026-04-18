<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Canais de notificação
            $table->boolean('email_notificacoes')->default(true);
            $table->boolean('push_notificacoes')->default(true);
            
            // Tipos de notificação
            $table->boolean('notificar_processos_novos')->default(true);
            $table->boolean('notificar_documentos_vencendo')->default(true);
            $table->boolean('notificar_prazos')->default(true);
            
            $table->timestamps();
            
            // Um usuário só pode ter uma preferência
            $table->unique('user_id');
            
            // Índice para performance
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};


