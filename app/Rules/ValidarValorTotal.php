<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidarValorTotal implements Rule
{
    protected $custoProduto;
    protected $custoFrete;
    
    public function __construct($custoProduto, $custoFrete)
    {
        $this->custoProduto = $custoProduto ?? 0;
        $this->custoFrete = $custoFrete ?? 0;
    }
    
    public function passes($attribute, $value)
    {
        if ($value === null) {
            return true; // Opcional
        }
        
        $totalEsperado = $this->custoProduto + $this->custoFrete;
        // Tolerância de 0.01 para arredondamento
        return abs($value - $totalEsperado) < 0.01;
    }
    
    public function message()
    {
        return 'O custo total deve ser igual à soma de custo_produto + custo_frete.';
    }
}

