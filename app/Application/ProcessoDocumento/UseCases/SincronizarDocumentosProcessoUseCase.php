<?php

namespace App\Application\ProcessoDocumento\UseCases;

use App\Application\ProcessoDocumento\DTOs\SincronizarDocumentosDTO;
use App\Domain\Processo\Entities\Processo;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoDocumento\Repositories\ProcessoDocumentoRepositoryInterface;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Use Case: Sincronizar documentos selecionados com o processo
 */
class SincronizarDocumentosProcessoUseCase
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
     * @param SincronizarDocumentosDTO $dto
     * @return void
     */
    public function executar(int $processoId, int $empresaId, SincronizarDocumentosDTO $dto): void
    {
        // Buscar processo
        $processo = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processo || $processo->empresa_id !== $empresaId) {
            throw new NotFoundException('Processo não encontrado ou não pertence à empresa.');
        }

        DB::transaction(function () use ($processo, $dto) {
            // Remover documentos não selecionados
            $idsSelecionados = $dto->getIdsSelecionados();
            $this->processoDocumentoRepository->deletarNaoSelecionados(
                $processo->id,
                $idsSelecionados
            );

            // Criar ou atualizar documentos selecionados
            foreach ($dto->documentos as $documentoId => $config) {
                // Validar que documento pertence à empresa
                $doc = $this->documentoHabilitacaoRepository->buscarModeloPorId($documentoId);
                if (!$doc || $doc->empresa_id !== $processo->empresa_id) {
                    throw new DomainException("Documento {$documentoId} não encontrado ou não pertence à empresa.");
                }

                // Buscar ou criar ProcessoDocumento
                $processoDoc = $this->processoDocumentoRepository->buscarModeloPorId(
                    $this->processoDocumentoRepository->existePorProcessoEDocumento($processo->id, $documentoId)
                        ? $this->processoDocumentoRepository->buscarPorIdEProcesso($processo->id, $processo)->id
                        : null
                );

                if ($processoDoc) {
                    $this->processoDocumentoRepository->atualizar($processoDoc, [
                        'exigido' => $config['exigido'] ?? true,
                        'disponivel_envio' => $config['disponivel_envio'] ?? false,
                        'status' => $config['status'] ?? 'pendente',
                        'observacoes' => $config['observacoes'] ?? null,
                    ]);
                } else {
                    $this->processoDocumentoRepository->criar([
                        'empresa_id' => $processo->empresa_id,
                        'processo_id' => $processo->id,
                        'documento_habilitacao_id' => $documentoId,
                        'exigido' => $config['exigido'] ?? true,
                        'disponivel_envio' => $config['disponivel_envio'] ?? false,
                        'status' => $config['status'] ?? 'pendente',
                        'observacoes' => $config['observacoes'] ?? null,
                        'documento_custom' => false,
                    ]);
                }
            }
        });
    }
}

