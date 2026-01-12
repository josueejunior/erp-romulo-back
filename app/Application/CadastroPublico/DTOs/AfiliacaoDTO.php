<?php

declare(strict_types=1);

namespace App\Application\CadastroPublico\DTOs;

/**
 * DTO para dados de afiliação no cadastro público
 */
final class AfiliacaoDTO
{
    public function __construct(
        public readonly int $afiliadoId,
        public readonly string $codigo,
        public readonly float $descontoAplicado, // Percentual de desconto aplicado
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            afiliadoId: (int) $data['afiliado_id'],
            codigo: $data['cupom_codigo'],
            descontoAplicado: isset($data['desconto_afiliado']) 
                ? (float) $data['desconto_afiliado'] 
                : 0.0,
        );
    }
}





