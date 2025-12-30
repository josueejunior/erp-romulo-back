<?php

namespace App\Application\Processo\Commands;

/**
 * Command para marcar processo como vencido
 */
class MarcarComoVencidoCommand
{
    public function __construct(
        public readonly int $processoId,
        public readonly int $empresaId,
        public readonly ?string $observacoes = null,
    ) {}
    
    public static function fromArray(array $dados): self
    {
        return new self(
            processoId: $dados['processo_id'],
            empresaId: $dados['empresa_id'],
            observacoes: $dados['observacoes'] ?? null,
        );
    }
}


