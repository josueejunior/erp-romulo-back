<?php

namespace App\Domain\Exceptions;

/**
 * Exceção para quando contrato não é encontrado
 */
class ContratoNaoEncontradoException extends NotFoundException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'Contrato não encontrado.');
    }
}




