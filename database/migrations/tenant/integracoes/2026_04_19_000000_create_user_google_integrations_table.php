<?php

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'user_google_integrations';

    public function up(): void
    {
        Schema::create('user_google_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignUserId();
            $table->text('refresh_token');
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->string('google_email', 255)->nullable();
            $table->string('scopes', 4000)->nullable();
            $table->timestamp('criado_em')->nullable();
            $table->timestamp('atualizado_em')->nullable();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_google_integrations');
    }
};
