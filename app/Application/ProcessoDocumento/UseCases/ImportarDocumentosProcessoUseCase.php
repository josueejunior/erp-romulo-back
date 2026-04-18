<?php

namespace App\Application\ProcessoDocumento\UseCases;

use App\Domain\Processo\Entities\Processo;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\ProcessoDocumento\Repositories\ProcessoDocumentoRepositoryInterface;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Use Case: Importar todos os documentos ativos da empresa para o processo
 */
class ImportarDocumentosProcessoUseCase
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
     * @return int Número de documentos importados
     */
    public function executar(int $processoId, int $empresaId): int
    {
        // Buscar processo
        $processo = $this->processoRepository->buscarModeloPorId($processoId);
        if (!$processo || $processo->empresa_id !== $empresaId) {
            throw new NotFoundException('Processo não encontrado ou não pertence à empresa.');
        }

        // Buscar documentos ativos da empresa
        $documentosAtivos = $this->documentoHabilitacaoRepository->buscarAtivosPorEmpresa($empresaId);

        $importados = 0;
        
        DB::transaction(function () use ($processo, $documentosAtivos, &$importados) {
            foreach ($documentosAtivos as $documento) {
                // Verificar se já existe
                $existe = $this->processoDocumentoRepository->existePorProcessoEDocumento(
                    $processo->id,
                    $documento->id
                );

                if (!$existe) {
                    $this->processoDocumentoRepository->criar([
                        'empresa_id' => $processo->empresa_id,
                        'processo_id' => $processo->id,
                        'documento_habilitacao_id' => $documento->id,
                        'exigido' => true,
                        'disponivel_envio' => false,
                        'status' => 'pendente',
                        'documento_custom' => false,
                    ]);
                    $importados++;
                }
            }
        });

        return $importados;
    }
}

