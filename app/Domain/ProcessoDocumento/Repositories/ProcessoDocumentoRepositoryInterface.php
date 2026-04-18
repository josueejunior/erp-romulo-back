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
     * @param \App\Modules\Processo\Models\Processo|Processo $processo
     */
    public function buscarPorIdEProcesso(int $id, $processo): ?\App\Domain\ProcessoDocumento\Entities\ProcessoDocumento;

    /**
     * Listar todos os documentos de um processo
     * @param \App\Modules\Processo\Models\Processo|Processo $processo
     */
    public function listarPorProcesso($processo): Collection;

    /**
     * Criar novo documento
     * @return \App\Modules\Processo\Models\ProcessoDocumento
     */
    public function criar(array $dados): \App\Modules\Processo\Models\ProcessoDocumento;

    /**
     * Atualizar documento existente
     * @param \App\Modules\Processo\Models\ProcessoDocumento $processoDocumento
     * @return \App\Modules\Processo\Models\ProcessoDocumento
     */
    public function atualizar(\App\Modules\Processo\Models\ProcessoDocumento $processoDocumento, array $dados): \App\Modules\Processo\Models\ProcessoDocumento;

    /**
     * Deletar documento
     * @param \App\Modules\Processo\Models\ProcessoDocumento $processoDocumento
     */
    public function deletar(\App\Modules\Processo\Models\ProcessoDocumento $processoDocumento): bool;

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

    /**
     * Buscar por processo e documento de habilitação
     */
    public function buscarPorProcessoEDocumento(int $processoId, int $documentoHabilitacaoId): ?\App\Modules\Processo\Models\ProcessoDocumento;
}

