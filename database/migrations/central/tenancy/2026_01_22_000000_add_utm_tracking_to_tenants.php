<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona campos de UTM tracking e fingerprint ao tenant
     * 
     * Permite rastrear origem de marketing e contexto de conversão
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('utm_source')->nullable()->after('logo')
                ->comment('Origem do tráfego (ex: google, facebook)');
            $table->string('utm_medium')->nullable()->after('utm_source')
                ->comment('Meio de marketing (ex: cpc, email, social)');
            $table->string('utm_campaign')->nullable()->after('utm_medium')
                ->comment('Nome da campanha (ex: black_friday, lancamento)');
            $table->string('utm_term')->nullable()->after('utm_campaign')
                ->comment('Termo de busca (para campanhas de busca)');
            $table->string('utm_content')->nullable()->after('utm_term')
                ->comment('Conteúdo específico (ex: botao_verde, banner_principal)');
            $table->string('fingerprint')->nullable()->after('utm_content')
                ->comment('Browser fingerprint para identificação única');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'fingerprint',
            ]);
        });
    }
};

