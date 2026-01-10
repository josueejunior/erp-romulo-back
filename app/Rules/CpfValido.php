<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida CPF com dígitos verificadores
 * 
 * 🔥 DDD: Regra de validação isolada e reutilizável
 */
class CpfValido implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        // CPF pode ser nullable/opcional em alguns contextos
        if (empty($value)) {
            return; // Deixar validação required para outras regras
        }

        // Limpar CPF (remover formatação)
        $cpf = preg_replace('/[^0-9]/', '', (string) $value);

        // Verificar tamanho
        if (strlen($cpf) !== 11) {
            $fail('O :attribute deve ter 11 dígitos.');
            return;
        }

        // Verificar se todos os dígitos são iguais (CPFs inválidos conhecidos)
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            $fail('O :attribute informado é inválido.');
            return;
        }

        // Validar primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += intval($cpf[$i]) * (10 - $i);
        }
        $digito1 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ($digito1 !== intval($cpf[9])) {
            $fail('O :attribute informado é inválido (dígito verificador incorreto).');
            return;
        }

        // Validar segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += intval($cpf[$i]) * (11 - $i);
        }
        $digito2 = ($soma % 11 < 2) ? 0 : 11 - ($soma % 11);
        if ($digito2 !== intval($cpf[10])) {
            $fail('O :attribute informado é inválido (dígito verificador incorreto).');
            return;
        }
    }
}

