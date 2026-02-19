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
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();

            // Quem fez
            $table->unsignedBigInteger('admin_id')->nullable()->index();

            // O que fez
            $table->string('action', 100)->index(); // ex: user.created, backup.created, tenant.schema_repair
            $table->string('resource_type', 100)->nullable(); // ex: user, backup, tenant
            $table->unsignedBigInteger('resource_id')->nullable()->index();

            // Em qual tenant/empresa
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('empresa_id')->nullable()->index();

            // Metadados de requisição
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Contexto extra (JSON)
            $table->json('context')->nullable();

            $table->timestamps();

            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};

