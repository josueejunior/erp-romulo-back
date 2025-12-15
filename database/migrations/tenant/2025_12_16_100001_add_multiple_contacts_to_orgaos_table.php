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
        Schema::table('orgaos', function (Blueprint $table) {
            // Adicionar campos para múltiplos emails e telefones (JSON)
            $table->json('emails')->nullable()->after('email');
            $table->json('telefones')->nullable()->after('telefone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orgaos', function (Blueprint $table) {
            $table->dropColumn(['emails', 'telefones']);
        });
    }
};

