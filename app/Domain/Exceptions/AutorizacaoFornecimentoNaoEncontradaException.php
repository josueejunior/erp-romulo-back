<?php

namespace App\Domain\Exceptions;

/**
 * Exceção para quando autorização de fornecimento não é encontrada
 */
class AutorizacaoFornecimentoNaoEncontradaException extends NotFoundException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'Autorização de Fornecimento não encontrada.');
    }
}








