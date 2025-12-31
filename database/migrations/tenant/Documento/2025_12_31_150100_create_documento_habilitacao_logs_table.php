<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'documento_habilitacao_logs';

    public function up(): void
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('documento_habilitacao_id')->constrained('documentos_habilitacao')->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('acao'); // create, update, view, download, delete
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table);
    }
};
