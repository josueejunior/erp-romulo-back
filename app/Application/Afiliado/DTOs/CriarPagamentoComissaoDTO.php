<?php

declare(strict_types=1);

namespace App\Application\Afiliado\DTOs;

/**
 * DTO para criar pagamento de comissões
 */
final class CriarPagamentoComissaoDTO
{
    public function __construct(
        public readonly int $afiliadoId,
        public readonly string $periodoCompetencia,
        public readonly array $comissaoIds,
        public readonly ?string $metodoPagamento = null,
        public readonly ?string $comprovante = null,
        public readonly ?string $observacoes = null,
        public readonly ?string $dataPagamento = null,
    ) {}

    public static function fromArray(array $data): self
    {
        // Garantir que comissao_ids seja sempre um array
        $comissaoIds = $data['comissao_ids'] ?? [];
        if (is_string($comissaoIds)) {
            $comissaoIds = json_decode($comissaoIds, true) ?? [];
        }
        if (!is_array($comissaoIds)) {
            $comissaoIds = [];
        }
        
        return new self(
            afiliadoId: (int) $data['afiliado_id'],
            periodoCompetencia: $data['periodo_competencia'],
            comissaoIds: $comissaoIds,
            metodoPagamento: $data['metodo_pagamento'] ?? null,
            comprovante: $data['comprovante'] ?? null,
            observacoes: $data['observacoes'] ?? null,
            dataPagamento: $data['data_pagamento'] ?? null,
        );
    }
}







