<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'documento_habilitacao_versoes';

    public function up(): void
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('documento_habilitacao_id')->constrained('documentos_habilitacao')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->string('nome_arquivo');
            $table->string('caminho');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('tamanho_bytes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};
