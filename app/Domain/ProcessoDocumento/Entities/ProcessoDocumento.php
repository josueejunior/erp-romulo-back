<?php

namespace App\Domain\ProcessoDocumento\Entities;

use App\Domain\Processo\Entities\Processo;
use App\Domain\DocumentoHabilitacao\Entities\DocumentoHabilitacao;

/**
 * Entidade de Domínio: ProcessoDocumento
 * 
 * Representa a vinculação de um documento de habilitação a um processo
 */
class ProcessoDocumento
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $empresaId,
        public readonly int $processoId,
        public readonly ?int $documentoHabilitacaoId,
        public readonly ?int $versaoDocumentoHabilitacaoId,
        public readonly bool $documentoCustom,
        public readonly ?string $tituloCustom,
        public readonly bool $exigido,
        public readonly bool $disponivelEnvio,
        public readonly string $status, // pendente, possui, anexado
        public readonly ?string $nomeArquivo,
        public readonly ?string $caminhoArquivo,
        public readonly ?string $mime,
        public readonly ?int $tamanhoBytes,
        public readonly ?string $observacoes,
    ) {}

    /**
     * Verificar se é documento customizado
     */
    public function isCustom(): bool
    {
        return $this->documentoCustom;
    }

    /**
     * Verificar se tem arquivo anexado
     */
    public function temArquivo(): bool
    {
        return !empty($this->caminhoArquivo);
    }

    /**
     * Verificar se pode ter versão
     */
    public function podeTerVersao(): bool
    {
        return !$this->documentoCustom && $this->documentoHabilitacaoId !== null;
    }

    /**
     * Verificar se status é anexado
     */
    public function isAnexado(): bool
    {
        return $this->status === 'anexado';
    }
}

