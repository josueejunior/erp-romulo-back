<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'orcamentos';

    public function up(): void
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('processo_id')->constrained('processos')->onDelete('cascade');
            $table->foreignId('fornecedor_id')->constrained('fornecedores')->onDelete('restrict');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};
