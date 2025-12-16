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
        Schema::table('processos', function (Blueprint $table) {
            // Adicionar campo forma_entrega (parcelado ou remessa_unica)
            $table->string('forma_entrega')->nullable()->after('endereco_entrega');
            
            // Adicionar campo prazo_entrega (texto formatado)
            $table->string('prazo_entrega')->nullable()->after('forma_entrega');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->dropColumn(['forma_entrega', 'prazo_entrega']);
        });
    }
};


