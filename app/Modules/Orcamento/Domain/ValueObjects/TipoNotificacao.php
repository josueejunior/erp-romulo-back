<?php

namespace App\Modules\Orcamento\Domain\ValueObjects;

class TipoNotificacao
{
    private string $tipo;

    private const TIPOS_VALIDOS = [
        'orcamento_criado',
        'orcamento_atualizado',
        'orcamento_aprovado',
        'orcamento_rejeitado',
        'preco_formacao_atualizado',
    ];

    public function __construct(string $tipo)
    {
        if (!in_array($tipo, self::TIPOS_VALIDOS)) {
            throw new \InvalidArgumentException('Tipo de notificação inválido: ' . $tipo);
        }
        $this->tipo = $tipo;
    }

    public function getValue(): string
    {
        return $this->tipo;
    }

    public function getTitulo(): string
    {
        return match($this->tipo) {
            'orcamento_criado' => 'Novo Orçamento',
            'orcamento_atualizado' => 'Orçamento Atualizado',
            'orcamento_aprovado' => 'Orçamento Aprovado',
            'orcamento_rejeitado' => 'Orçamento Rejeitado',
            'preco_formacao_atualizado' => 'Preço de Formação Atualizado',
            default => 'Notificação'
        };
    }

    public function __toString(): string
    {
        return $this->tipo;
    }
}
