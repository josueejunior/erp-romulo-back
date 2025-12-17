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
        // Adicionar empresa_id em processos
        if (!Schema::hasColumn('processos', 'empresa_id')) {
            Schema::table('processos', function (Blueprint $table) {
                $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->onDelete('cascade');
            });
        }

        // Adicionar empresa_id em orcamentos
        if (!Schema::hasColumn('orcamentos', 'empresa_id')) {
            Schema::table('orcamentos', function (Blueprint $table) {
                $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->onDelete('cascade');
            });
        }

        // Adicionar empresa_id em contratos
        if (!Schema::hasColumn('contratos', 'empresa_id')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->onDelete('cascade');
            });
        }

        // Adicionar empresa_id em empenhos
        if (!Schema::hasColumn('empenhos', 'empresa_id')) {
            Schema::table('empenhos', function (Blueprint $table) {
                $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->onDelete('cascade');
            });
        }

        // Adicionar empresa_id em notas_fiscais
        if (!Schema::hasColumn('notas_fiscais', 'empresa_id')) {
            Schema::table('notas_fiscais', function (Blueprint $table) {
                $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->onDelete('cascade');
            });
        }

        // Adicionar empresa_id em autorizacoes_fornecimento
        if (!Schema::hasColumn('autorizacoes_fornecimento', 'empresa_id')) {
            Schema::table('autorizacoes_fornecimento', function (Blueprint $table) {
                $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->onDelete('cascade');
            });
        }

        // Adicionar empresa_id em fornecedores
        if (!Schema::hasColumn('fornecedores', 'empresa_id')) {
            Schema::table('fornecedores', function (Blueprint $table) {
                $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            if (Schema::hasColumn('fornecedores', 'empresa_id')) {
                $table->dropForeign(['empresa_id']);
                $table->dropColumn('empresa_id');
            }
        });

        Schema::table('autorizacoes_fornecimento', function (Blueprint $table) {
            if (Schema::hasColumn('autorizacoes_fornecimento', 'empresa_id')) {
                $table->dropForeign(['empresa_id']);
                $table->dropColumn('empresa_id');
            }
        });

        Schema::table('notas_fiscais', function (Blueprint $table) {
            if (Schema::hasColumn('notas_fiscais', 'empresa_id')) {
                $table->dropForeign(['empresa_id']);
                $table->dropColumn('empresa_id');
            }
        });

        Schema::table('empenhos', function (Blueprint $table) {
            if (Schema::hasColumn('empenhos', 'empresa_id')) {
                $table->dropForeign(['empresa_id']);
                $table->dropColumn('empresa_id');
            }
        });

        Schema::table('contratos', function (Blueprint $table) {
            if (Schema::hasColumn('contratos', 'empresa_id')) {
                $table->dropForeign(['empresa_id']);
                $table->dropColumn('empresa_id');
            }
        });

        Schema::table('orcamentos', function (Blueprint $table) {
            if (Schema::hasColumn('orcamentos', 'empresa_id')) {
                $table->dropForeign(['empresa_id']);
                $table->dropColumn('empresa_id');
            }
        });

        Schema::table('processos', function (Blueprint $table) {
            if (Schema::hasColumn('processos', 'empresa_id')) {
                $table->dropForeign(['empresa_id']);
                $table->dropColumn('empresa_id');
            }
        });
    }
};

