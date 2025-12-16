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
        Schema::table('formacao_precos', function (Blueprint $table) {
            $table->foreignId('orcamento_item_id')->nullable()->after('orcamento_id')->constrained('orcamento_itens')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formacao_precos', function (Blueprint $table) {
            $table->dropForeign(['orcamento_item_id']);
            $table->dropColumn('orcamento_item_id');
        });
    }
};


