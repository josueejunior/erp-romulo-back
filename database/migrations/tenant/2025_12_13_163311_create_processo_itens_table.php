<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'processo_itens';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('processo_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->integer('numero_item');
            $table->string('codigo_interno', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->decimal('quantidade', 15, 2);
            $table->string('unidade', Blueprint::VARCHAR_SMALL);
            $table->observacao('especificacao_tecnica');
            $table->text('observacoes_edital')->nullable();
            $table->string('marca_modelo_referencia', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->boolean('exige_atestado')->default(false);
            $table->integer('quantidade_minima_atestado')->nullable();
            $table->integer('quantidade_atestado_cap_tecnica')->nullable();
            $table->decimal('valor_estimado', 15, 2)->nullable();
            $table->decimal('valor_estimado_total', 15, 2)->nullable();
            $table->string('fonte_valor', Blueprint::VARCHAR_SMALL)->nullable(); // 'edital' ou 'pesquisa'
            $table->decimal('valor_final_sessao', 15, 2)->nullable();
            $table->date('data_disputa')->nullable();
            $table->decimal('valor_arrematado', 15, 2)->nullable();
            $table->decimal('valor_negociado', 15, 2)->nullable();
            $table->decimal('valor_minimo_venda', 15, 2)->nullable();
            $table->integer('classificacao')->nullable();
            $table->status([
                'pendente',
                'aceito',
                'aceito_habilitado',
                'desclassificado',
                'inabilitado'
            ], 'pendente', 'status_item');
            $table->enum('situacao_final', ['vencido', 'perdido'])->nullable();
            $table->enum('chance_arremate', ['baixa', 'media', 'alta'])->nullable();
            $table->integer('chance_percentual')->nullable();
            $table->boolean('tem_chance')->default(true);
            // Campos financeiros (calculados)
            $table->decimal('valor_vencido', 15, 2)->default(0);
            $table->decimal('valor_empenhado', 15, 2)->default(0);
            $table->decimal('valor_faturado', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->decimal('saldo_aberto', 15, 2)->default(0);
            $table->decimal('lucro_bruto', 15, 2)->default(0);
            $table->decimal('lucro_liquido', 15, 2)->default(0);
            $table->observacao('lembretes');
            $table->observacao('observacoes');
            $table->datetimes();
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
