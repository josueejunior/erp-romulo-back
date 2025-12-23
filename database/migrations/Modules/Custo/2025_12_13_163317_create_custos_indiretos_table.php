<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'custo_indiretos';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('custo_indiretos', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->descricao('descricao');
            $table->date('data');
            $table->decimal('valor', 15, 2);
            $table->string('categoria', Blueprint::VARCHAR_SMALL)->nullable();
            $table->observacao('observacoes');
            $table->datetimesWithSoftDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custo_indiretos');
    }
};
