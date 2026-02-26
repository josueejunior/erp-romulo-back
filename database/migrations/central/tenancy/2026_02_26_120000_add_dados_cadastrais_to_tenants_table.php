<?php

declare(strict_types=1);

use App\Database\Migrations\Migration;
use App\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public string $table = 'tenants';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Dados básicos adicionais
            $table->string('nome_fantasia', Blueprint::VARCHAR_DEFAULT)->nullable()->after('razao_social');

            // Contatos adicionais
            $table->string('telefone_fixo', 20)->nullable()->after('telefones');
            $table->string('email_financeiro', Blueprint::VARCHAR_DEFAULT)->nullable()->after('email');
            $table->string('email_licitacao', Blueprint::VARCHAR_DEFAULT)->nullable()->after('email_financeiro');
            $table->string('site', Blueprint::VARCHAR_DEFAULT)->nullable()->after('cep');

            // Dados fiscais
            $table->string('inscricao_estadual', 50)->nullable()->after('cnpj');
            $table->string('inscricao_municipal', 50)->nullable()->after('inscricao_estadual');
            $table->string('cnae_principal', 32)->nullable()->after('inscricao_municipal');
            $table->date('data_abertura')->nullable()->after('cnae_principal');

            // Representante legal detalhado
            $table->string('representante_legal_rg', 50)->nullable()->after('representante_legal_cpf');
            $table->string('representante_legal_telefone', 20)->nullable()->after('representante_legal_rg');
            $table->string('representante_legal_email', Blueprint::VARCHAR_DEFAULT)->nullable()->after('representante_legal_telefone');

            // Dados bancários complementares
            $table->string('favorecido_razao_social', Blueprint::VARCHAR_DEFAULT)->nullable()->after('pix');
            $table->string('favorecido_cnpj', 18)->nullable()->after('favorecido_razao_social');

            // Responsáveis internos
            $table->string('responsavel_comercial', Blueprint::VARCHAR_DEFAULT)->nullable()->after('favorecido_cnpj');
            $table->string('responsavel_financeiro', Blueprint::VARCHAR_DEFAULT)->nullable()->after('responsavel_comercial');
            $table->string('responsavel_licitacoes', Blueprint::VARCHAR_DEFAULT)->nullable()->after('responsavel_financeiro');

            // Informações complementares
            $table->string('ramo_atuacao', Blueprint::VARCHAR_DEFAULT)->nullable()->after('responsavel_licitacoes');
            $table->text('principais_produtos_servicos')->nullable()->after('ramo_atuacao');
            $table->text('marcas_trabalhadas')->nullable()->after('principais_produtos_servicos');
            $table->text('observacoes')->nullable()->after('marcas_trabalhadas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'nome_fantasia',
                'telefone_fixo',
                'email_financeiro',
                'email_licitacao',
                'site',
                'inscricao_estadual',
                'inscricao_municipal',
                'cnae_principal',
                'data_abertura',
                'representante_legal_rg',
                'representante_legal_telefone',
                'representante_legal_email',
                'favorecido_razao_social',
                'favorecido_cnpj',
                'responsavel_comercial',
                'responsavel_financeiro',
                'responsavel_licitacoes',
                'ramo_atuacao',
                'principais_produtos_servicos',
                'marcas_trabalhadas',
                'observacoes',
            ]);
        });
    }
};

