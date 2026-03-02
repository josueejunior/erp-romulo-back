<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'oportunidade_itens';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            // Garantir que numero_orcamento seja string (identificação textual do orçamento)
            $table->string('numero_orcamento', Blueprint::VARCHAR_DEFAULT)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable($this->table)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            // Fallback: volta para inteiro (caso tenha sido assim originalmente)
            $table->integer('numero_orcamento')->nullable()->change();
        });
    }
};

