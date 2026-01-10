<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona a coluna excluido_em para soft delete se não existir.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'excluido_em')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('excluido_em')->nullable()->after('atualizado_em');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'excluido_em')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('excluido_em');
            });
        }
    }
};

