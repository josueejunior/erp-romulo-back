<?php

namespace App\Http\Requests\ProcessoItem;

use App\Domain\ProcessoItem\Enums\UnidadeMedida;
use App\Modules\Processo\Models\Processo;
use App\Rules\DbTypeRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request para criação de item de processo
 * 
 * ✅ DDD: Encapsula validação, removendo responsabilidade do Controller
 */
class ProcessoItemCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $processoId = (int) $this->route('processo');
        if ($processoId < 1) {
            throw new HttpResponseException(response()->json(['message' => 'Processo não encontrado'], 404));
        }

        // Antes das regras de validação: processo inexistente ou fora da empresa (escopo BelongsToEmpresa) → 404
        if (! Processo::query()->whereKey($processoId)->exists()) {
            throw new HttpResponseException(response()->json(['message' => 'Processo não encontrado'], 404));
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'fornecedor_id' => ['nullable', 'exists:fornecedores,id'],
            'transportadora_id' => ['nullable', 'exists:fornecedores,id'],
            'numero_item' => [DbTypeRule::nullable(), ...DbTypeRule::integer(), 'min:1'],
            'codigo_interno' => [DbTypeRule::nullable(), ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)],
            'quantidade' => ['required', 'numeric', 'min:0.01'],
            'unidade' => ['required', 'string', 'in:' . implode(',', UnidadeMedida::values())],
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









