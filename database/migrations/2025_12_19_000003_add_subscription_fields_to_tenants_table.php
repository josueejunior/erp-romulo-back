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
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('plano_atual_id')->nullable()->after('status')->constrained('planos')->onDelete('set null');
            $table->foreignId('assinatura_atual_id')->nullable()->after('plano_atual_id')->constrained('assinaturas')->onDelete('set null');
            $table->integer('limite_processos')->nullable()->after('assinatura_atual_id'); // Cache do plano
            $table->integer('limite_usuarios')->nullable()->after('limite_processos'); // Cache do plano
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plano_atual_id']);
            $table->dropForeign(['assinatura_atual_id']);
            $table->dropColumn(['plano_atual_id', 'assinatura_atual_id', 'limite_processos', 'limite_usuarios']);
        });
    }
};

