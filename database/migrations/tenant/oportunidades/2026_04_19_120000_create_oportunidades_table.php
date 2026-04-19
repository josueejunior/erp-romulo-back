<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'oportunidades';

    public function up(): void
    {
        Schema::create('oportunidades', function (Blueprint $table) {
            $table->id();
            $table->foreignEmpresa();
            $table->string('modalidade', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->string('numero', Blueprint::VARCHAR_DEFAULT)->nullable();
            $table->text('objeto_resumido')->nullable();
            $table->string('link_oportunidade', 2048)->nullable();
            $table->json('itens')->nullable();
            $table->string('pncp_numero_controle', 120)->nullable()->index();
            $table->json('pncp_snapshot')->nullable();
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oportunidades');
    }
};
