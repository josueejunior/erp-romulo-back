<?php

namespace App\Application\ProcessoDocumento\DTOs;

/**
 * DTO para atualização de documento de processo
 */
class AtualizarDocumentoProcessoDTO
{
    public function __construct(
        public readonly ?bool $exigido = null,
        public readonly ?bool $disponivelEnvio = null,
        public readonly ?string $status = null, // pendente, possui, anexado
        public readonly ?string $observacoes = null,
        public readonly ?int $versaoDocumentoHabilitacaoId = null,
    ) {}

    /**
     * Criar DTO a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            exigido: isset($data['exigido']) ? (bool) $data['exigido'] : null,
            disponivelEnvio: isset($data['disponivel_envio']) ? (bool) $data['disponivel_envio'] : null,
            status: $data['status'] ?? null,
            observacoes: $data['observacoes'] ?? null,
            versaoDocumentoHabilitacaoId: isset($data['versao_documento_habilitacao_id']) 
                ? (int) $data['versao_documento_habilitacao_id'] 
                : null,
        );
    }

    /**
     * Verificar se há dados para atualizar
     */
    public function temDados(): bool
    {
        return $this->exigido !== null
            || $this->disponivelEnvio !== null
            || $this->status !== null
            || $this->observacoes !== null
            || $this->versaoDocumentoHabilitacaoId !== null;
    }
}

