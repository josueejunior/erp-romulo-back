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
            $table->string('status')->default('pendente')->after('disponivel_envio');
            $table->unsignedBigInteger('versao_documento_habilitacao_id')->nullable()->after('documento_habilitacao_id');
            $table->boolean('documento_custom')->default(false)->after('documento_habilitacao_id');
            $table->string('titulo_custom')->nullable()->after('documento_custom');
            $table->string('nome_arquivo')->nullable()->after('status');
            $table->string('caminho_arquivo')->nullable()->after('nome_arquivo');
            $table->string('mime')->nullable()->after('caminho_arquivo');
            $table->unsignedBigInteger('tamanho_bytes')->nullable()->after('mime');
            $table->text('observacoes')->nullable()->change();

            $table->foreign('versao_documento_habilitacao_id', 'proc_doc_versao_fk')
                ->references('id')
                ->on('documento_habilitacao_versoes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            $table->dropForeign('proc_doc_versao_fk');
            $table->dropColumn([
                'status',
                'versao_documento_habilitacao_id',
                'documento_custom',
                'titulo_custom',
                'nome_arquivo',
                'caminho_arquivo',
                'mime',
                'tamanho_bytes',
            ]);
            $table->text('observacoes')->nullable(false)->change();
        });
    }
};
