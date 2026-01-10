<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida CNPJ com dígitos verificadores
 */
class CnpjValido implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (empty($value)) {
            $fail('O campo :attribute é obrigatório.');
            return;
        }

        // Limpar CNPJ (remover formatação)
        $cnpj = preg_replace('/[^0-9]/', '', (string) $value);

        // Verificar tamanho
        if (strlen($cnpj) !== 14) {
            $fail('O :attribute deve ter 14 dígitos.');
            return;
        }

        // Verificar se todos os dígitos são iguais (CNPJs inválidos conhecidos)
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            $fail('O :attribute informado é inválido.');
            return;
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
            $fail('O :attribute informado é inválido (dígito verificador incorreto).');
            return;
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
            $fail('O :attribute informado é inválido (dígito verificador incorreto).');
            return;
        }
    }
}

