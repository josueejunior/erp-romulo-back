<?php

namespace App\Application\Processo\Commands;

/**
 * Command para mover processo para julgamento
 * 
 * Implementa Command Pattern para ações complexas
 */
class MoverParaJulgamentoCommand
{
    public function __construct(
        public readonly int $processoId,
        public readonly array $itensData,
        public readonly int $empresaId,
    ) {}
    
    /**
     * Criar a partir de array de dados
     */
    public static function fromArray(array $dados): self
    {
        return new self(
            processoId: $dados['processo_id'],
            itensData: $dados['itens'] ?? [],
            empresaId: $dados['empresa_id'],
        );
    }
}

