<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormacaoPrecoResource extends JsonResource
{
    private function value(string $camelCase, ?string $snakeCase = null, mixed $default = null): mixed
    {
        $snakeCase ??= strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $camelCase) ?? $camelCase);

        $value = data_get($this->resource, $camelCase);
        if ($value !== null) {
            return $value;
        }

        $value = data_get($this->resource, $snakeCase);

        return $value ?? $default;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->value('id'),
            'custo_produto' => (float) $this->value('custoProduto', 'custo_produto', 0),
            'frete' => (float) $this->value('frete', null, 0),
            'percentual_impostos' => (float) $this->value('percentualImpostos', 'percentual_impostos', 0),
            'valor_impostos' => (float) $this->value('valorImpostos', 'valor_impostos', 0),
            'percentual_margem' => (float) $this->value('percentualMargem', 'percentual_margem', 0),
            'valor_margem' => (float) $this->value('valorMargem', 'valor_margem', 0),
            'preco_minimo' => (float) $this->value('precoMinimo', 'preco_minimo', 0),
            'preco_recomendado' => $this->value('precoRecomendado', 'preco_recomendado') ? (float) $this->value('precoRecomendado', 'preco_recomendado') : null,
            'observacoes' => $this->value('observacoes'),
        ];
    }
}
