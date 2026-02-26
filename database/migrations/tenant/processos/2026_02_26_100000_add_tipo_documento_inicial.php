<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'processo_documentos';

    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            $table->string('tipo_documento_inicial', 32)->nullable()->after('titulo_custom');
            $table->index('tipo_documento_inicial');
        });
    }

    public function down(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            $table->dropIndex(['tipo_documento_inicial']);
            $table->dropColumn('tipo_documento_inicial');
        });
    }
};
