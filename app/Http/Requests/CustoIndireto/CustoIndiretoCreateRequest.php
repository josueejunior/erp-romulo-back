<?php

namespace App\Http\Requests\CustoIndireto;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para criação de custo indireto
 * 
 * ✅ DDD: Encapsula validação, removendo responsabilidade do Controller
 */
class CustoIndiretoCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // A autorização será feita no Use Case
    }

    public function rules(): array
    {
        return [
            'descricao' => ['required', 'string', 'max:255'],
            'data' => ['required', 'date'],
            'valor' => ['required', 'numeric', 'min:0'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}







