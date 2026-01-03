<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'orgao_responsaveis';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orgao_responsaveis', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->foreignId('orgao_id')->constrained('orgaos')->onDelete('cascade');
            $table->string('nome', Blueprint::VARCHAR_DEFAULT);
            $table->string('cargo', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->json('emails')->nullable(); // Array de emails
            $table->json('telefones')->nullable(); // Array de telefones
            $table->observacao('observacoes');
            $table->datetimes();
            $table->timestamp(Blueprint::DELETED_AT)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orgao_responsaveis');
    }
};


