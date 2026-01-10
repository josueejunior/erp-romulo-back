<?php

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Exceptions\DomainException;

/**
 * Value Object: CNPJ
 * Garante que CNPJs sejam sempre válidos e formatados
 */
readonly class Cnpj
{
    public function __construct(
        public string $value
    ) {
        $this->validate();
        $this->value = $this->normalize();
    }

    private function validate(): void
    {
        // Remover formatação para validação
        $cnpj = preg_replace('/[^0-9]/', '', $this->value);

        if (strlen($cnpj) !== 14) {
            throw new DomainException('CNPJ deve ter 14 dígitos.');
        }

        // Validar dígitos verificadores
        if (!$this->validarDigitosVerificadores($cnpj)) {
            throw new DomainException('CNPJ inválido: dígitos verificadores incorretos.');
        }
    }

    private function validarDigitosVerificadores(string $cnpj): bool
    {
        // Verificar se todos os dígitos são iguais
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        // Validar primeiro dígito verificador
        $soma = 0;
        $peso = 5;
        for ($i = 0; $i < 12; $i++) {
            $soma += intval($cnpj[$i]) * $peso;
            $peso = ($peso === 2) ? 9 : $peso - 1;
        }
        $digito1 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ($digito1 !== intval($cnpj[12])) {
            return false;
        }

        // Validar segundo dígito verificador
        $soma = 0;
        $peso = 6;
        for ($i = 0; $i < 13; $i++) {
            $soma += intval($cnpj[$i]) * $peso;
            $peso = ($peso === 2) ? 9 : $peso - 1;
        }
        $digito2 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ($digito2 !== intval($cnpj[13])) {
            return false;
        }

        return true;
    }

    private function normalize(): string
    {
        // Retornar apenas números
        return preg_replace('/[^0-9]/', '', $this->value);
    }

    /**
     * Formatar CNPJ (XX.XXX.XXX/XXXX-XX)
     */
    public function formatado(): string
    {
        return preg_replace(
            '/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/',
            '$1.$2.$3/$4-$5',
            $this->value
        );
    }

    public function equals(Cnpj $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}




