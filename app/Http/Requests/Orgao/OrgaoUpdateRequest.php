<?php

namespace App\Http\Requests\Orgao;

use Illuminate\Foundation\Http\FormRequest;

class OrgaoUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uasg' => ['nullable', 'string', 'max:50'],
            'razao_social' => ['nullable', 'string', 'max:250'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'cep' => ['nullable', 'string', 'max:10'],
            'logradouro' => ['nullable', 'string', 'max:250'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'size:2'],
            'email' => ['nullable', 'email', 'max:250'],
            'telefone' => ['nullable', 'string', 'max:15'],
            'emails' => ['nullable', 'array'],
            'telefones' => ['nullable', 'array'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}


