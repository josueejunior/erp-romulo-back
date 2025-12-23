<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'empresa_user';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('empresa_user', function (Blueprint $table) {
            $table->id();
            // Tabela pivot - não usa foreignEmpresa() pois não precisa de ->after('id')
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignUserId(); // user_id -> users (padrão Laravel)
            $table->string('perfil', Blueprint::VARCHAR_SMALL)->default('consulta'); // admin, operacional, financeiro, consulta
            $table->datetimes();
            
            $table->unique(['empresa_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresa_user');
    }
};
