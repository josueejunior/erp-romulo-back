<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'orcamento_itens';

    public function up(): void
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('orcamento_id')->constrained('orcamentos')->onDelete('cascade');
            $table->foreignId('processo_item_id')->constrained('processo_itens')->onDelete('cascade');
            $table->longText('especificacao_tecnica_customizada')->nullable();
            $table->string('marca_modelo_referencia')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};
