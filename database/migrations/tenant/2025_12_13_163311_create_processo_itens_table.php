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
        Schema::create('processo_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->integer('numero_item');
            $table->decimal('quantidade', 15, 2);
            $table->string('unidade');
            $table->text('especificacao_tecnica');
            $table->string('marca_modelo_referencia')->nullable();
            $table->boolean('exige_atestado')->default(false);
            $table->integer('quantidade_minima_atestado')->nullable();
            $table->decimal('valor_estimado', 15, 2)->nullable();
            $table->decimal('valor_final_sessao', 15, 2)->nullable();
            $table->decimal('valor_negociado', 15, 2)->nullable();
            $table->integer('classificacao')->nullable();
            $table->enum('status_item', [
                'pendente',
                'aceito',
                'aceito_habilitado',
                'desclassificado',
                'inabilitado'
            ])->default('pendente');
            $table->enum('chance_arremate', ['baixa', 'media', 'alta'])->nullable();
            $table->integer('chance_percentual')->nullable();
            $table->text('lembretes')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processo_itens');
    }
};
