<?php

namespace App\Modules\Orcamento\Domain\ValueObjects;

class MensagemNotificacao
{
    private string $mensagem;
    private int $tamanhoMaximo = 500;

    public function __construct(string $mensagem)
    {
        if (empty($mensagem)) {
            throw new \InvalidArgumentException('Mensagem não pode estar vazia');
        }
        if (strlen($mensagem) > $this->tamanhoMaximo) {
            throw new \InvalidArgumentException('Mensagem não pode exceder ' . $this->tamanhoMaximo . ' caracteres');
        }

        $this->mensagem = trim($mensagem);
    }

    public function getValue(): string
    {
        return $this->mensagem;
    }

    public function getTamanho(): int
    {
        return strlen($this->mensagem);
    }

    public function __toString(): string
    {
        return $this->mensagem;
    }
}
