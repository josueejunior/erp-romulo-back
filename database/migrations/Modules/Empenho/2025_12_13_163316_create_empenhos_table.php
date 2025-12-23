<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'empenhos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('empenhos', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->onDelete('set null');
            $table->foreignId('autorizacao_fornecimento_id')->nullable()->constrained('autorizacoes_fornecimento')->onDelete('set null');
            $table->string('numero', Blueprint::VARCHAR_DEFAULT);
            $table->date('data');
            $table->date('data_recebimento')->nullable();
            $table->date('prazo_entrega_calculado')->nullable();
            $table->decimal('valor', 15, 2);
            $table->boolean('concluido')->default(false);
            $table->status([
                'aguardando_entrega',
                'em_atendimento',
                'atendido',
                'atrasado',
                'concluido'
            ], 'aguardando_entrega', 'situacao');
            $table->date('data_entrega')->nullable();
            $table->string('numero_cte', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->observacao('observacoes');
            $table->datetimesWithSoftDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empenhos');
    }
};
