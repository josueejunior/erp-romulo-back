<?php

namespace App\Application\Assinatura\DTOs;

/**
 * DTO para resposta de plano
 */
class PlanoResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $nome,
        public readonly ?string $descricao = null,
        public readonly ?float $precoMensal = null,
        public readonly ?float $precoAnual = null,
        public readonly ?int $limiteProcessos = null,
        public readonly ?int $limiteUsuarios = null,
        public readonly ?int $limiteArmazenamentoMb = null,
    ) {}

    /**
     * Converter para array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'preco_mensal' => $this->precoMensal,
            'preco_anual' => $this->precoAnual,
            'limite_processos' => $this->limiteProcessos,
            'limite_usuarios' => $this->limiteUsuarios,
            'limite_armazenamento_mb' => $this->limiteArmazenamentoMb,
        ];
    }
}


