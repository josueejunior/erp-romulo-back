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
        if (!Schema::hasTable('orgaos')) {
            return;
        }

        if (!Schema::hasColumn('orgaos', 'empresa_id')) {
            Schema::table('orgaos', function (Blueprint $table) {
                $table->foreignId('empresa_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('empresas')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orgaos') && Schema::hasColumn('orgaos', 'empresa_id')) {
            Schema::table('orgaos', function (Blueprint $table) {
                $table->dropForeign(['empresa_id']);
                $table->dropColumn('empresa_id');
            });
        }
    }
};

