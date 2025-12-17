<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidarSomaValores implements Rule
{
    protected $valores;
    protected $totalEsperado;
    protected $tolerancia;
    protected $campoTotal;
    
    public function __construct($valores, $totalEsperado, $campoTotal = 'valor_total', $tolerancia = 0.01)
    {
        $this->valores = is_array($valores) ? $valores : [$valores];
        $this->totalEsperado = $totalEsperado;
        $this->campoTotal = $campoTotal;
        $this->tolerancia = $tolerancia;
    }
    
    public function passes($attribute, $value)
    {
        if ($value === null) {
            return true; // Opcional
        }
        
        $soma = array_sum(array_map(function($v) {
            return is_numeric($v) ? (float)$v : 0;
        }, $this->valores));
        
        // Tolerância para arredondamento
        $diferenca = abs($value - $soma);
        return $diferenca < $this->tolerancia;
    }
    
    public function message()
    {
        $soma = array_sum(array_map(function($v) {
            return is_numeric($v) ? (float)$v : 0;
        }, $this->valores));
        
        return "O {$this->campoTotal} ({$this->totalEsperado}) deve ser igual à soma dos valores (" . number_format($soma, 2, ',', '.') . ").";
    }
}
