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
        Schema::create('custos_indiretos', function (Blueprint $table) {
            $table->id();
            // empresa_id removido - cada tenant tem seu próprio banco
            $table->string('descricao');
            $table->date('data');
            $table->decimal('valor', 15, 2);
            $table->string('categoria')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custos_indiretos');
    }
};
