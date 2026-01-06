<?php

namespace App\Http\Requests\Fornecedor;

use Illuminate\Foundation\Http\FormRequest;

class FornecedorUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'razao_social' => 'sometimes|required|string|max:255',
            'cnpj' => 'nullable|string|max:18',
            'nome_fantasia' => 'nullable|string|max:255',
            'cep' => 'nullable|string|max:10',
            'logradouro' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:20',
            'bairro' => 'nullable|string|max:255',
            'complemento' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:2',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'emails' => 'nullable|array',
            'telefones' => 'nullable|array',
            'contato' => 'nullable|string|max:255',
            'observacoes' => 'nullable|string',
            'is_transportadora' => 'nullable|boolean',
        ];
    }
}


