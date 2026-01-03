<?php

namespace App\Modules\Processo\Services;

use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoDocumento;
use App\Modules\Documento\Models\DocumentoHabilitacao;
use App\Modules\Documento\Models\DocumentoHabilitacaoVersao;
use App\Modules\Documento\Services\DocumentoHabilitacaoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

/**
 * Service para gerenciar documentos de habilitação vinculados a processos
 */
class ProcessoDocumentoService
{
    /**
     * Importar lista de documentos pré-cadastrados e selecionar quais são necessários
     * 
     * @param Processo $processo
     * @param array $documentosSelecionados Array com ['documento_id' => ['exigido' => bool, 'disponivel_envio' => bool]]
     * @return void
     */
    public function sincronizarDocumentos(Processo $processo, array $documentosSelecionados): void
    {
        DB::transaction(function () use ($processo, $documentosSelecionados) {
            // Remover documentos não selecionados
            $idsSelecionados = array_keys($documentosSelecionados);
            ProcessoDocumento::where('processo_id', $processo->id)
                ->whereNotIn('documento_habilitacao_id', $idsSelecionados)
                ->delete();

            // Criar ou atualizar documentos selecionados
            foreach ($documentosSelecionados as $documentoId => $config) {
                // Garantir que o documento pertence à empresa
                $doc = DocumentoHabilitacao::where('id', $documentoId)
                    ->where('empresa_id', $processo->empresa_id)
                    ->firstOrFail();

                ProcessoDocumento::updateOrCreate(
                    [
                        'processo_id' => $processo->id,
                        'documento_habilitacao_id' => $documentoId,
                        'empresa_id' => $processo->empresa_id,
                    ],
                    [
                        'exigido' => $config['exigido'] ?? true,
                        'disponivel_envio' => $config['disponivel_envio'] ?? false,
                        'status' => $config['status'] ?? 'pendente',
                        'observacoes' => $config['observacoes'] ?? null,
                    ]
                );

                $this->logDocAction($doc, 'vincular_processo', [
                    'processo_id' => $processo->id,
                    'status' => $config['status'] ?? 'pendente',
                ]);
            }
        });
    }

    /**
     * Importar todos os documentos ativos da empresa para o processo
     * 
     * @param Processo $processo
     * @return int Número de documentos importados
     */
    public function importarTodosDocumentosAtivos(Processo $processo): int
    {
        $documentosAtivos = DocumentoHabilitacao::where('empresa_id', $processo->empresa_id)
            ->where('ativo', true)
            ->get();

        $importados = 0;
        foreach ($documentosAtivos as $documento) {
            $existe = ProcessoDocumento::where('processo_id', $processo->id)
                ->where('documento_habilitacao_id', $documento->id)
                ->exists();

            if (!$existe) {
                ProcessoDocumento::create([
                    'processo_id' => $processo->id,
                    'documento_habilitacao_id' => $documento->id,
                    'empresa_id' => $processo->empresa_id,
                    'exigido' => true,
                    'disponivel_envio' => false,
                    'status' => 'pendente',
                ]);
                $importados++;

                $this->logDocAction($documento, 'vincular_processo', [
                    'processo_id' => $processo->id,
                    'status' => 'pendente',
                ]);
            }
        }

        return $importados;
    }

    /**
     * Obter documentos do processo com informações de vencimento
     * 
     * @param Processo $processo
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obterDocumentosComStatus(Processo $processo)
    {
        return ProcessoDocumento::where('processo_id', $processo->id)
            ->with(['documentoHabilitacao', 'versaoDocumento'])
            ->get()
            ->map(function ($processoDocumento) {
                $doc = $processoDocumento->documentoHabilitacao;
                $versoes = $doc?->versoes()->latest('versao')->limit(5)->get();
                return [
                    'id' => $processoDocumento->id,
                    'documento_habilitacao_id' => $processoDocumento->documento_habilitacao_id,
                    'versao_documento_habilitacao_id' => $processoDocumento->versao_documento_habilitacao_id,
                    'versao_selecionada' => $processoDocumento->versaoDocumento,
                    'tipo' => $doc->tipo ?? null,
                    'numero' => $doc->numero ?? null,
                    'data_validade' => $doc->data_validade ?? null,
                    'status_vencimento' => $doc->status_vencimento ?? 'sem_data',
                    'dias_para_vencer' => $doc->dias_para_vencer ?? null,
                    'exigido' => $processoDocumento->exigido,
                    'disponivel_envio' => $processoDocumento->disponivel_envio,
                    'status' => $processoDocumento->status,
                    'documento_custom' => $processoDocumento->documento_custom,
                    'titulo_custom' => $processoDocumento->titulo_custom,
                    'arquivo' => [
                        'nome' => $processoDocumento->nome_arquivo,
                        'caminho' => $processoDocumento->caminho_arquivo,
                        'mime' => $processoDocumento->mime,
                        'tamanho_bytes' => $processoDocumento->tamanho_bytes,
                    ],
                    'observacoes' => $processoDocumento->observacoes,
                    'versoes' => $versoes,
                ];
            });
    }

    public function atualizarDocumentoProcesso(Processo $processo, int $processoDocumentoId, array $data, ?UploadedFile $arquivo = null): ProcessoDocumento
    {
        $procDoc = ProcessoDocumento::where('processo_id', $processo->id)
            ->where('empresa_id', $processo->empresa_id)
            ->where('id', $processoDocumentoId)
            ->firstOrFail();

        // Validar versão se enviada
        if (!empty($data['versao_documento_habilitacao_id'])) {
            DocumentoHabilitacaoVersao::where('id', $data['versao_documento_habilitacao_id'])
                ->where('documento_habilitacao_id', $procDoc->documento_habilitacao_id)
                ->firstOrFail();
        }

        $payload = [
            'exigido' => $data['exigido'] ?? $procDoc->exigido,
            'disponivel_envio' => $data['disponivel_envio'] ?? $procDoc->disponivel_envio,
            'status' => $data['status'] ?? $procDoc->status,
            'observacoes' => $data['observacoes'] ?? $procDoc->observacoes,
            'versao_documento_habilitacao_id' => $data['versao_documento_habilitacao_id'] ?? $procDoc->versao_documento_habilitacao_id,
        ];

        if ($arquivo instanceof UploadedFile) {
            $fileName = time() . '_' . $arquivo->getClientOriginalName();
            $path = $arquivo->storeAs("processos/{$processo->id}/documentos", $fileName, 'public');
            $payload['nome_arquivo'] = $fileName;
            $payload['caminho_arquivo'] = $path;
            $payload['mime'] = $arquivo->getMimeType();
            $payload['tamanho_bytes'] = $arquivo->getSize();
            $payload['status'] = 'anexado';
        }

        $procDoc->update($payload);

        if ($procDoc->documento_habilitacao_id) {
            $doc = DocumentoHabilitacao::find($procDoc->documento_habilitacao_id);
            if ($doc) {
                $this->logDocAction($doc, 'atualizar_processo', [
                    'processo_id' => $processo->id,
                    'status' => $procDoc->status,
                    'versao_documento_habilitacao_id' => $procDoc->versao_documento_habilitacao_id,
                ]);
            }
        }

        return $procDoc->fresh(['versaoDocumento', 'documentoHabilitacao']);
    }

    public function criarDocumentoCustom(Processo $processo, array $data, ?UploadedFile $arquivo = null): ProcessoDocumento
    {
        $payload = [
            'empresa_id' => $processo->empresa_id,
            'processo_id' => $processo->id,
            'documento_custom' => true,
            'titulo_custom' => $data['titulo_custom'] ?? 'Documento',
            'exigido' => $data['exigido'] ?? true,
            'disponivel_envio' => $data['disponivel_envio'] ?? false,
            'status' => $data['status'] ?? 'pendente',
            'observacoes' => $data['observacoes'] ?? null,
        ];

        if ($arquivo instanceof UploadedFile) {
            $fileName = time() . '_' . $arquivo->getClientOriginalName();
            $path = $arquivo->storeAs("processos/{$processo->id}/documentos", $fileName, 'public');
            $payload['nome_arquivo'] = $fileName;
            $payload['caminho_arquivo'] = $path;
            $payload['mime'] = $arquivo->getMimeType();
            $payload['tamanho_bytes'] = $arquivo->getSize();
            $payload['status'] = 'anexado';
        }

        return ProcessoDocumento::create($payload);
    }

    public function baixarArquivo(Processo $processo, int $processoDocumentoId): ?array
    {
        $procDoc = ProcessoDocumento::where('processo_id', $processo->id)
            ->where('empresa_id', $processo->empresa_id)
            ->where('id', $processoDocumentoId)
            ->firstOrFail();

        $path = $procDoc->caminho_arquivo;
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        if ($procDoc->documento_habilitacao_id) {
            $doc = DocumentoHabilitacao::find($procDoc->documento_habilitacao_id);
            if ($doc) {
                $this->logDocAction($doc, 'download_processo', [
                    'processo_id' => $processo->id,
                    'processo_documento_id' => $procDoc->id,
                ]);
            }
        }

        return [
            'path' => $path,
            'nome' => $procDoc->nome_arquivo ?? basename($path),
            'mime' => $procDoc->mime ?? 'application/octet-stream',
        ];
    }

    protected function logDocAction(DocumentoHabilitacao $doc, string $acao, array $meta = []): void
    {
        try {
            app(DocumentoHabilitacaoService::class)->logAction($doc, $acao, $meta);
        } catch (\Throwable $e) {
            \Log::warning('Falha ao registrar log de documento em processo', [
                'erro' => $e->getMessage(),
                'doc_id' => $doc->id,
                'acao' => $acao,
            ]);
        }
    }
}


