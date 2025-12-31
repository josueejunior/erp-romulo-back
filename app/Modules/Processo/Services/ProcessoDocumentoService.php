<?php

namespace App\Modules\Processo\Services;

use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoDocumento;
use App\Modules\Documento\Models\DocumentoHabilitacao;
use Illuminate\Support\Facades\DB;

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
                ProcessoDocumento::updateOrCreate(
                    [
                        'processo_id' => $processo->id,
                        'documento_habilitacao_id' => $documentoId,
                        'empresa_id' => $processo->empresa_id,
                    ],
                    [
                        'exigido' => $config['exigido'] ?? true,
                        'disponivel_envio' => $config['disponivel_envio'] ?? false,
                        'observacoes' => $config['observacoes'] ?? null,
                    ]
                );
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
                ]);
                $importados++;
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
            ->with('documentoHabilitacao')
            ->get()
            ->map(function ($processoDocumento) {
                $doc = $processoDocumento->documentoHabilitacao;
                return [
                    'id' => $processoDocumento->id,
                    'documento_habilitacao_id' => $processoDocumento->documento_habilitacao_id,
                    'tipo' => $doc->tipo ?? null,
                    'numero' => $doc->numero ?? null,
                    'data_validade' => $doc->data_validade ?? null,
                    'status_vencimento' => $doc->status_vencimento ?? 'sem_data',
                    'dias_para_vencer' => $doc->dias_para_vencer ?? null,
                    'exigido' => $processoDocumento->exigido,
                    'disponivel_envio' => $processoDocumento->disponivel_envio,
                    'observacoes' => $processoDocumento->observacoes,
                ];
            });
    }
}

