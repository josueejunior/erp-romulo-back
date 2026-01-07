<?php

namespace App\Domain\ProcessoDocumento\Repositories;

use App\Domain\Processo\Entities\Processo;
use App\Domain\ProcessoDocumento\Entities\ProcessoDocumento;
use Illuminate\Support\Collection;

/**
 * Repository Interface para ProcessoDocumento
 * 
 * Define contratos para persistência e consulta de documentos de processo
 */
interface ProcessoDocumentoRepositoryInterface
{
    /**
     * Buscar documento por ID
     */
    public function buscarPorId(int $id): ?ProcessoDocumento;

    /**
     * Buscar documento por ID e processo (com validação de empresa)
     */
    public function buscarPorIdEProcesso(int $id, Processo $processo): ?ProcessoDocumento;

    /**
     * Listar todos os documentos de um processo
     */
    public function listarPorProcesso(Processo $processo): Collection;

    /**
     * Criar novo documento
     */
    public function criar(array $dados): ProcessoDocumento;

    /**
     * Atualizar documento existente
     */
    public function atualizar(ProcessoDocumento $processoDocumento, array $dados): ProcessoDocumento;

    /**
     * Deletar documento
     */
    public function deletar(ProcessoDocumento $processoDocumento): bool;

    /**
     * Verificar se documento existe para processo e documento_habilitacao_id
     */
    public function existePorProcessoEDocumento(int $processoId, ?int $documentoHabilitacaoId): bool;

    /**
     * Deletar documentos não selecionados
     */
    public function deletarNaoSelecionados(int $processoId, array $idsSelecionados): int;

    /**
     * Buscar modelo Eloquent por ID (compatibilidade)
     */
    public function buscarModeloPorId(int $id): ?\App\Modules\Processo\Models\ProcessoDocumento;
}

