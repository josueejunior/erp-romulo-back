<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidarVinculoProcesso implements Rule
{
    protected $processoId;
    protected $tipo;
    
    public function __construct($processoId, $tipo)
    {
        $this->processoId = $processoId;
        $this->tipo = $tipo; // 'contrato', 'empenho', 'af'
    }
    
    public function passes($attribute, $value)
    {
        if (!$value) {
            return true; // Opcional
        }
        
        switch ($this->tipo) {
            case 'contrato':
                $doc = \App\Models\Contrato::find($value);
                break;
            case 'empenho':
                $doc = \App\Models\Empenho::find($value);
                break;
            case 'af':
                $doc = \App\Models\AutorizacaoFornecimento::find($value);
                break;
            default:
                return false;
        }
        
        return $doc && $doc->processo_id === $this->processoId;
    }
    
    public function message()
    {
        $tipoLabel = match($this->tipo) {
            'contrato' => 'Contrato',
            'empenho' => 'Empenho',
            'af' => 'Autorização de Fornecimento',
            default => 'Documento'
        };
        
        return "O {$tipoLabel} selecionado não pertence a este processo.";
    }
}

