<?php

namespace App\Application\ProcessoDocumento\DTOs;

/**
 * DTO para criação de documento customizado
 */
class CriarDocumentoCustomDTO
{
    public function __construct(
        public readonly string $tituloCustom,
        public readonly bool $exigido,
        public readonly bool $disponivelEnvio,
        public readonly string $status, // pendente, possui, anexado
        public readonly ?string $observacoes = null,
    ) {}

    /**
     * Criar DTO a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tituloCustom: $data['titulo_custom'] ?? 'Documento',
            exigido: (bool) ($data['exigido'] ?? true),
            disponivelEnvio: (bool) ($data['disponivel_envio'] ?? false),
            status: $data['status'] ?? 'pendente',
            observacoes: $data['observacoes'] ?? null,
        );
    }
}

