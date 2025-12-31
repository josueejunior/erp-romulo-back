<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'processos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->foreignId('orgao_responsavel_id')
                ->nullable()
                ->after('orgao_id')
                ->constrained('orgao_responsaveis')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->dropForeign(['orgao_responsavel_id']);
            $table->dropColumn('orgao_responsavel_id');
        });
    }
};

