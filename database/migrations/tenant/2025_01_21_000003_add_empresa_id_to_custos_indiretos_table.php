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
        if (!Schema::hasTable('custo_indiretos')) {
            return;
        }

        if (!Schema::hasColumn('custo_indiretos', 'empresa_id')) {
            Schema::table('custo_indiretos', function (Blueprint $table) {
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
        if (Schema::hasTable('custo_indiretos') && Schema::hasColumn('custo_indiretos', 'empresa_id')) {
            Schema::table('custo_indiretos', function (Blueprint $table) {
                $table->dropForeign(['empresa_id']);
                $table->dropColumn('empresa_id');
            });
        }
    }
};

