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

        $paymentMethod = $this->input('payment_method', 'credit_card');
        
        $rules = [
            'plano_id' => 'required|integer|exists:planos,id',
            'periodo' => 'required|string|in:mensal,anual',
            'payer_email' => 'required|email',
            'payer_cpf' => $paymentMethod === 'pix' 
                ? ['required', 'string', new \App\Rules\CpfValido()] // PIX requer CPF obrigatório
                : ['nullable', 'string', new \App\Rules\CpfValido()], // Cartão: CPF opcional mas se fornecido deve ser válido
            'payment_method' => 'nullable|string|in:credit_card,pix,boleto', // Método de pagamento
            'cupom_codigo' => 'nullable|string|max:50', // Código do cupom de desconto
        ];

        // Validações para planos pagos
        if (!$isGratis) {
            // Se for cartão de crédito, token é obrigatório
            if ($paymentMethod === 'credit_card') {
                $rules['card_token'] = 'required|string';
                $rules['installments'] = 'nullable|integer|min:1|max:12';
            }
            
            // Se for PIX, não precisa de token mas CPF é obrigatório (já validado acima)
            if ($paymentMethod === 'pix') {
                // PIX não precisa de card_token
            }
        }

        return $rules;
    }
}

