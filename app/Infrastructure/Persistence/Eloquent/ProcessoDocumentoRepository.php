<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\ProcessoDocumento\Repositories\ProcessoDocumentoRepositoryInterface;
use App\Domain\Processo\Entities\Processo;
use App\Modules\Processo\Models\ProcessoDocumento as ProcessoDocumentoModel;
use Illuminate\Support\Collection;

/**
 * Implementação Eloquent do ProcessoDocumentoRepository
 */
class ProcessoDocumentoRepository implements ProcessoDocumentoRepositoryInterface
{
    public function buscarPorId(int $id): ?\App\Domain\ProcessoDocumento\Entities\ProcessoDocumento
    {
        $model = ProcessoDocumentoModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorIdEProcesso(int $id, Processo $processo): ?\App\Domain\ProcessoDocumento\Entities\ProcessoDocumento
    {
        $model = ProcessoDocumentoModel::where('id', $id)
            ->where('processo_id', $processo->id)
            ->first();
        return $model ? $this->toDomain($model) : null;
    }

    public function listarPorProcesso(Processo $processo): Collection
    {
        $models = ProcessoDocumentoModel::where('processo_id', $processo->id)
            ->with(['documentoHabilitacao', 'versaoDocumento'])
            ->get();

        return $models->map(fn($model) => $this->toDomain($model));
    }

    public function criar(array $dados): ProcessoDocumentoModel
    {
        return ProcessoDocumentoModel::create($dados);
    }

    public function atualizar(ProcessoDocumentoModel $processoDocumento, array $dados): ProcessoDocumentoModel
    {
        $processoDocumento->update($dados);
        return $processoDocumento->fresh();
    }

    public function deletar(ProcessoDocumentoModel $processoDocumento): bool
    {
        return $processoDocumento->delete();
    }

    public function existePorProcessoEDocumento(int $processoId, ?int $documentoHabilitacaoId): bool
    {
        return ProcessoDocumentoModel::where('processo_id', $processoId)
            ->where('documento_habilitacao_id', $documentoHabilitacaoId)
            ->exists();
    }

    public function deletarNaoSelecionados(int $processoId, array $idsSelecionados): int
    {
        $query = ProcessoDocumentoModel::where('processo_id', $processoId);
        
        if (!empty($idsSelecionados)) {
            $query->whereNotIn('documento_habilitacao_id', $idsSelecionados);
        }
        
        return $query->delete();
    }

    public function buscarModeloPorId(int $id): ?ProcessoDocumentoModel
    {
        return ProcessoDocumentoModel::find($id);
    }

    /**
     * Converter modelo Eloquent para entidade de domínio
     */
    private function toDomain(ProcessoDocumentoModel $model): \App\Domain\ProcessoDocumento\Entities\ProcessoDocumento
    {
        return new \App\Domain\ProcessoDocumento\Entities\ProcessoDocumento(
            id: $model->id,
            empresaId: $model->empresa_id,
            processoId: $model->processo_id,
            documentoHabilitacaoId: $model->documento_habilitacao_id,
            versaoDocumentoHabilitacaoId: $model->versao_documento_habilitacao_id,
            documentoCustom: $model->documento_custom ?? false,
            tituloCustom: $model->titulo_custom,
            exigido: $model->exigido ?? false,
            disponivelEnvio: $model->disponivel_envio ?? false,
            status: $model->status ?? 'pendente',
            nomeArquivo: $model->nome_arquivo,
            caminhoArquivo: $model->caminho_arquivo,
            mime: $model->mime,
            tamanhoBytes: $model->tamanho_bytes,
            observacoes: $model->observacoes,
        );
    }
}

