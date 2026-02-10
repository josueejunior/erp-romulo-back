<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        // Criar tabela primeiro sem a foreign key
        Schema::create('onboarding_progress', function (Blueprint $table) {
            $table->id();
            // Criar coluna tenant_id sem foreign key inicialmente
            $table->unsignedBigInteger('tenant_id')->nullable();
            // user_id não pode ter foreign key porque a tabela 'users' está no banco do tenant, não no central
            $table->unsignedBigInteger('user_id')->nullable(); // Usuário que está fazendo onboarding (no banco do tenant)
            $table->string('session_id')->nullable()->index(); // Para rastrear antes do cadastro
            $table->string('email')->nullable()->index(); // Email do lead (antes do cadastro)
            $table->boolean('onboarding_concluido')->default(false)->index();
            $table->json('etapas_concluidas')->nullable(); // Array de etapas concluídas
            $table->json('checklist')->nullable(); // Checklist de ações realizadas
            $table->integer('progresso_percentual')->default(0); // 0-100
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index(['tenant_id', 'onboarding_concluido']);
            $table->index(['user_id', 'onboarding_concluido']);
            $table->index(['session_id', 'onboarding_concluido']);
        });

        // Adicionar foreign key de forma segura após criar a tabela
        // Verificar se a tabela tenants existe antes de adicionar foreign key
        if (Schema::hasTable('tenants')) {
            Schema::table('onboarding_progress', function (Blueprint $table) {
                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->onDelete('cascade');
            });
        } else {
            Log::warning("Migration: Tabela 'tenants' não existe, pulando foreign key para tenant_id em onboarding_progress.");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_progress');
    }
};

