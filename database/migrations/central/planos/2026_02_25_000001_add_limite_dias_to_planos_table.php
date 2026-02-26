<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'planos';

    /**
     * Run the migrations.
     * Limite de dias da assinatura: null = padrão (gratuito 3 dias, pago 30); 0 = ilimitado; >0 = N dias.
     */
    public function up(): void
    {
        if (Schema::hasColumn($this->table, 'limite_dias')) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            $table->integer('limite_dias')->nullable()->after('limite_armazenamento_mb')
                ->comment('Duração em dias: null=padrão, 0=ilimitado, >0=N dias');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn($this->table, 'limite_dias')) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table) {
            $table->dropColumn('limite_dias');
        });
    }
};
