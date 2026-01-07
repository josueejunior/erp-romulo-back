<?php

namespace App\Application\ProcessoDocumento\DTOs;

/**
 * DTO para sincronização de documentos
 * 
 * Array com ['documento_id' => ['exigido' => bool, 'disponivel_envio' => bool, 'status' => string, 'observacoes' => string]]
 */
class SincronizarDocumentosDTO
{
    /**
     * @param array<int, array{exigido?: bool, disponivel_envio?: bool, status?: string, observacoes?: string}> $documentos
     */
    public function __construct(
        public readonly array $documentos,
    ) {}

    /**
     * Criar DTO a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            documentos: $data['documentos'] ?? [],
        );
    }

    /**
     * Obter IDs dos documentos selecionados
     * 
     * @return array<int>
     */
    public function getIdsSelecionados(): array
    {
        return array_keys($this->documentos);
    }
}

