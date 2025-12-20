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
            // Endereço
            $table->string('endereco')->nullable()->after('email');
            $table->string('cidade')->nullable()->after('endereco');
            $table->string('estado', 2)->nullable()->after('cidade');
            $table->string('cep', 10)->nullable()->after('estado');
            
            // Telefones (JSON para múltiplos)
            $table->json('telefones')->nullable()->after('cep');
            
            // Emails adicionais (JSON para múltiplos)
            $table->json('emails_adicionais')->nullable()->after('email');
            
            // Dados bancários
            $table->string('banco')->nullable()->after('emails_adicionais');
            $table->string('agencia')->nullable()->after('banco');
            $table->string('conta')->nullable()->after('agencia');
            $table->string('tipo_conta')->nullable()->after('conta'); // corrente, poupanca
            $table->string('pix')->nullable()->after('tipo_conta');
            
            // Representante legal
            $table->string('representante_legal_nome')->nullable()->after('pix');
            $table->string('representante_legal_cpf')->nullable()->after('representante_legal_nome');
            $table->string('representante_legal_cargo')->nullable()->after('representante_legal_cpf');
            
            // Logo
            $table->string('logo')->nullable()->after('representante_legal_cargo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'endereco',
                'cidade',
                'estado',
                'cep',
                'telefones',
                'emails_adicionais',
                'banco',
                'agencia',
                'conta',
                'tipo_conta',
                'pix',
                'representante_legal_nome',
                'representante_legal_cpf',
                'representante_legal_cargo',
                'logo',
            ]);
        });
    }
};




