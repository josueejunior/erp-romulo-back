<?php

declare(strict_types=1);

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'tenants';

    /**
     * Run the migrations.
     * 
     * Remove campos limite_processos e limite_usuarios do tenant
     * pois esses limites vêm do plano da assinatura do usuário, não do tenant.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['limite_processos', 'limite_usuarios']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->integer('limite_processos')->nullable();
            $table->integer('limite_usuarios')->nullable();
        });
    }
};

