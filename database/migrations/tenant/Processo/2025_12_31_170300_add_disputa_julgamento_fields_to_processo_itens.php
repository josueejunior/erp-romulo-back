<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'processo_itens';

    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            $table->decimal('valor_final_pos_disputa', 15, 2)->nullable()->after('valor_estimado');
            $table->decimal('valor_negociado_pos_julgamento', 15, 2)->nullable()->after('valor_final_pos_disputa');
            $table->string('status_item')->default('pendente')->after('status');
            // pendente, aceito, aceito_habilitado, desclassificado, inabilitado
        });
    }

    public function down(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            $table->dropColumn(['valor_final_pos_disputa', 'valor_negociado_pos_julgamento', 'status_item']);
        });
    }
};
