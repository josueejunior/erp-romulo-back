<?php

namespace App\Domain\Exceptions;

use Exception;

/**
 * Exceção lançada quando o banco de dados do tenant já existe
 * e contém o próximo número disponível para tentar novamente
 */
class DatabaseAlreadyExistsException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $proximoNumeroDisponivel,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}


