<?php

namespace App\Application\ProcessoDocumento\UseCases;

use App\Domain\Processo\Entities\Processo;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoDocumento\Repositories\ProcessoDocumentoRepositoryInterface;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use Illuminate\Support\Collection;

/**
 * Use Case: Listar documentos de um processo
 * 
 * Retorna documentos com informações de vencimento e versões
 */
class ListarDocumentosProcessoUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
        private ProcessoDocumentoRepositoryInterface $processoDocumentoRepository,
        private DocumentoHabilitacaoRepositoryInterface $documentoHabilitacaoRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * @param int $processoId
     * @param int $empresaId
     * @return Collection
     */
    public function executar(int $processoId, int $empresaId): Collection
    {
        // Buscar processo
        $processo = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processo || $processo->empresa_id !== $empresaId) {
            throw new NotFoundException('Processo não encontrado ou não pertence à empresa.');
        }

        // Buscar documentos do processo
        $processoDocumentos = $this->processoDocumentoRepository->listarPorProcesso($processo);

        // Mapear para formato de resposta com informações adicionais
        return $processoDocumentos->map(function ($processoDocumento) use ($empresaId) {
            $doc = null;
            $versoes = collect([]);

            // Se não for customizado, buscar documento de habilitação e versões
            if ($processoDocumento->documento_habilitacao_id) {
                $doc = $this->documentoHabilitacaoRepository->buscarModeloPorId(
                    $processoDocumento->documento_habilitacao_id
                );

                if ($doc) {
                    // Buscar versões (últimas 5)
                    $versoes = $doc->versoes()
                        ->latest('versao')
                        ->limit(5)
                        ->get();
                }
            }

            return [
                'id' => $processoDocumento->id,
                'documento_habilitacao_id' => $processoDocumento->documento_habilitacao_id,
                'versao_documento_habilitacao_id' => $processoDocumento->versao_documento_habilitacao_id,
                'versao_selecionada' => $processoDocumento->versaoDocumento ?? null,
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
}

