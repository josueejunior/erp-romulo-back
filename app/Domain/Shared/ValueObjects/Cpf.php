<?php

namespace App\Domain\Shared\ValueObjects;

use DomainException;

/**
 * Value Object: CPF
 * Garante que CPFs sejam sempre válidos e formatados
 */
readonly class Cpf
{
    public function __construct(
        public string $value
    ) {
        $this->validate();
        $this->value = $this->normalize();
    }

    private function validate(): void
    {
        $cpf = preg_replace('/[^0-9]/', '', $this->value);

        if (strlen($cpf) !== 11) {
            throw new DomainException('CPF deve ter 11 dígitos.');
        }

        if (!$this->validarDigitosVerificadores($cpf)) {
            throw new DomainException('CPF inválido: dígitos verificadores incorretos.');
        }
    }

    private function validarDigitosVerificadores(string $cpf): bool
    {
        // Verificar se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Validar primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += intval($cpf[$i]) * (10 - $i);
        }
        $digito1 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ($digito1 !== intval($cpf[9])) {
            return false;
        }

        // Validar segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += intval($cpf[$i]) * (11 - $i);
        }
        $digito2 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ($digito2 !== intval($cpf[10])) {
            return false;
        }

        return true;
    }

    private function normalize(): string
    {
        return preg_replace('/[^0-9]/', '', $this->value);
    }

    /**
     * Formatar CPF (XXX.XXX.XXX-XX)
     */
    public function formatado(): string
    {
        return preg_replace(
            '/(\d{3})(\d{3})(\d{3})(\d{2})/',
            '$1.$2.$3-$4',
            $this->value
        );
    }

    public function equals(Cpf $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}



