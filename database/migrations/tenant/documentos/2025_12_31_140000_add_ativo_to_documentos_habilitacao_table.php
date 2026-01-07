<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'documentos_habilitacao';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documentos_habilitacao', function (Blueprint $table) {
            if (!Schema::hasColumn('documentos_habilitacao', 'ativo')) {
                $table->boolean('ativo')->default(true)->after('arquivo');
                
                // ⚡ Índice para performance
                $table->index('ativo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos_habilitacao', function (Blueprint $table) {
            if (Schema::hasColumn('documentos_habilitacao', 'ativo')) {
                $table->dropColumn('ativo');
            }
        });
    }
};


