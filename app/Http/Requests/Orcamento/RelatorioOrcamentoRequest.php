<?php

namespace App\Http\Requests\Orcamento;

use Illuminate\Foundation\Http\FormRequest;

class RelatorioOrcamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'status' => 'nullable|string|in:escolhido,pendente',
            'fornecedor' => 'nullable|integer|exists:fornecedores,id',
            'processo' => 'nullable|integer|exists:processos,id',
            'formato' => 'nullable|string|in:json,csv',
        ];
    }

    public function messages(): array
    {
        return [
            'data_fim.after_or_equal' => 'A data fim deve ser maior ou igual à data início.',
            'status.in' => 'O status deve ser "escolhido" ou "pendente".',
            'fornecedor.exists' => 'O fornecedor informado não existe.',
            'processo.exists' => 'O processo informado não existe.',
            'formato.in' => 'O formato deve ser "json" ou "csv".',
        ];
    }
}







