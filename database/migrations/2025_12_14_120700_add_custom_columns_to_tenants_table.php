<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('razao_social')->nullable()->after('id');
            $table->string('cnpj')->nullable()->after('razao_social');
            $table->string('email')->nullable()->after('cnpj');
            $table->string('status')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['razao_social', 'cnpj', 'email', 'status']);
        });
    }
};



