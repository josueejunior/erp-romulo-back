<?php

namespace App\Application\Orcamento\DTOs;

use Illuminate\Support\Collection;

/**
 * Read Model / DTO de saída para relatórios de orçamentos
 * 
 * ✅ DDD: Objeto semântico ao invés de array genérico
 * Facilita evolução e versionamento
 */
class RelatorioOrcamentosResult
{
    public function __construct(
        public readonly string $titulo,
        public readonly Collection $dados,
        public readonly int $totalRegistros,
        public readonly float $valorTotal,
        public readonly float $valorMedio,
        public readonly array $filtros = [],
        public readonly ?array $resumo = null,
    ) {}

    public function toArray(): array
    {
        $result = [
            'titulo' => $this->titulo,
            'filtros' => $this->filtros,
            'total_registros' => $this->totalRegistros,
            'valor_total' => $this->valorTotal,
            'valor_medio' => $this->valorMedio,
            'dados' => $this->dados->toArray(),
        ];

        if ($this->resumo) {
            $result['resumo'] = $this->resumo;
        }

        return $result;
    }
}




