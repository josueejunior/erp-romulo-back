<?php

namespace App\Domain\Exceptions;

/**
 * Exceção para quando formação de preço não é encontrada
 */
class FormacaoPrecoNaoEncontradaException extends NotFoundException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'Formação de preço não encontrada.');
    }
}









