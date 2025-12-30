<?php

namespace App\Http\Requests\Processo;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmarPagamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data_recebimento' => 'nullable|date',
        ];
    }
}

