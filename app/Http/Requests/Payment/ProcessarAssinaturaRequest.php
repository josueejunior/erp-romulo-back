<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class ProcessarAssinaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Verificar se o plano é gratuito
        $planoId = $this->input('plano_id');
        $periodo = $this->input('periodo', 'mensal');
        
        $isGratis = false;
        if ($planoId) {
            $plano = \App\Modules\Assinatura\Models\Plano::find($planoId);
            if ($plano) {
                $valor = $periodo === 'anual' ? $plano->preco_anual : $plano->preco_mensal;
                $isGratis = ($valor === null || $valor == 0);
            }
        }

        $rules = [
            'plano_id' => 'required|integer|exists:planos,id',
            'periodo' => 'required|string|in:mensal,anual',
            'payer_email' => 'required|email',
            'payer_cpf' => 'nullable|string',
        ];

        // Card token só é obrigatório se o plano não for gratuito
        if (!$isGratis) {
            $rules['card_token'] = 'required|string';
            $rules['installments'] = 'nullable|integer|min:1|max:12';
        }

        return $rules;
    }
}

