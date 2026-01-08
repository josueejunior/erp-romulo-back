<?php

namespace App\Http\Requests\ProcessoItem;

use App\Rules\DbTypeRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request para criação de item de processo
 * 
 * ✅ DDD: Encapsula validação, removendo responsabilidade do Controller
 */
class ProcessoItemCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // A autorização será feita no Use Case
    }

    public function rules(): array
    {
        return [
            'fornecedor_id' => ['nullable', 'exists:fornecedores,id'],
            'transportadora_id' => ['nullable', 'exists:fornecedores,id'],
            'numero_item' => [DbTypeRule::nullable(), ...DbTypeRule::integer(), 'min:1'],
            'codigo_interno' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'quantidade' => ['required', 'numeric', 'min:0.01'],
            'unidade' => ['required', ...DbTypeRule::string(50)],
            'especificacao_tecnica' => ['required', ...DbTypeRule::text()],
            'marca_modelo_referencia' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'observacoes_edital' => [DbTypeRule::nullable(), ...DbTypeRule::text()],
            'exige_atestado' => [...DbTypeRule::boolean()],
            'quantidade_minima_atestado' => [DbTypeRule::nullable(), ...DbTypeRule::integer(), 'min:1', 'required_if:exige_atestado,1'],
            'quantidade_atestado_cap_tecnica' => [DbTypeRule::nullable(), ...DbTypeRule::integer(), 'min:1'],
            'valor_estimado' => [DbTypeRule::nullable(), 'numeric', 'min:0'],
            'observacoes' => [DbTypeRule::nullable(), ...DbTypeRule::observacao()],
        ];
    }
}

