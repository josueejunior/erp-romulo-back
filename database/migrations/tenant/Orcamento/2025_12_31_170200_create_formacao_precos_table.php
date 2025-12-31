<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'formacao_precos';

    public function up(): void
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->foreignId('processo_item_id')->constrained('processo_itens')->onDelete('cascade');
            $table->foreignId('orcamento_id')->nullable()->constrained('orcamentos')->nullOnDelete();
            $table->decimal('custo_material', 15, 2)->default(0);
            $table->decimal('custo_frete', 15, 2)->default(0);
            $table->decimal('imposto_percentual', 8, 4)->default(0);
            $table->decimal('margem_lucro_percentual', 8, 4)->default(0);
            $table->decimal('valor_minimo_venda', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};
