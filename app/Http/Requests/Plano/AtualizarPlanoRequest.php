<?php

namespace App\Http\Requests\Plano;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AtualizarPlanoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Validação de autorização feita no middleware
    }

    public function rules(): array
    {
        $planoId = $this->route('plano');

        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('planos', 'nome')->ignore($planoId)],
            'descricao' => 'nullable|string|max:1000',
            'preco_mensal' => 'sometimes|required|numeric|min:0',
            'preco_anual' => 'nullable|numeric|min:0',
            'limite_processos' => 'nullable|integer|min:0',
            'restricao_diaria' => 'nullable|boolean',
            'limite_usuarios' => 'nullable|integer|min:0',
            'limite_armazenamento_mb' => 'nullable|integer|min:0',
            'recursos_disponiveis' => 'nullable|array',
            'recursos_disponiveis.*' => 'string',
            'ativo' => 'nullable|boolean',
            'ordem' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do plano é obrigatório.',
            'nome.unique' => 'Já existe um plano com este nome.',
            'preco_mensal.required' => 'O preço mensal é obrigatório.',
            'preco_mensal.numeric' => 'O preço mensal deve ser um número.',
            'preco_mensal.min' => 'O preço mensal não pode ser negativo.',
            'preco_anual.numeric' => 'O preço anual deve ser um número.',
            'preco_anual.min' => 'O preço anual não pode ser negativo.',
            'limite_processos.integer' => 'O limite de processos deve ser um número inteiro.',
            'limite_usuarios.integer' => 'O limite de usuários deve ser um número inteiro.',
            'limite_armazenamento_mb.integer' => 'O limite de armazenamento deve ser um número inteiro.',
        ];
    }
}

