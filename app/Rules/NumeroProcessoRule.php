<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida o formato do número de processo/modalidade
 * 
 * Formato aceito: XXXXX/YYYY onde:
 * - XXXXX = número sequencial (1 a 5 dígitos)
 * - YYYY = ano (4 dígitos, começando com 20)
 * 
 * Exemplos válidos: 123/2024, 00001/2025, 12345/2026
 */
class NumeroProcessoRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Campo opcional, se vazio não valida
        }
        
        // Padrão: 1-5 dígitos, barra, ano começando com 20XX
        $pattern = '/^\d{1,5}\/20\d{2}$/';
        
        if (!preg_match($pattern, $value)) {
            $fail('O :attribute deve estar no formato XXXXX/YYYY (ex: 00123/2025).');
        }
    }
    
    /**
     * Validar formato estático (para uso em entidades de domínio)
     */
    public static function isValid(?string $value): bool
    {
        if (empty($value)) {
            return true; // Campo opcional
        }
        
        return preg_match('/^\d{1,5}\/20\d{2}$/', $value) === 1;
    }
    
    /**
     * Formatar número de processo (adiciona zeros à esquerda se necessário)
     */
    public static function format(string $numero, int $digitos = 5): string
    {
        if (!str_contains($numero, '/')) {
            return $numero;
        }
        
        [$seq, $ano] = explode('/', $numero);
        $seq = str_pad($seq, $digitos, '0', STR_PAD_LEFT);
        
        return "{$seq}/{$ano}";
    }
}
