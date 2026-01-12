<?php

declare(strict_types=1);

namespace App\Application\Calendario\DTOs;

use Carbon\Carbon;

/**
 * DTO para buscar calendário de julgamento
 */
final class BuscarCalendarioJulgamentoDTO
{
    public function __construct(
        public readonly int $empresaId,
        public readonly ?Carbon $dataInicio = null,
        public readonly ?Carbon $dataFim = null,
    ) {}

    /**
     * Cria DTO a partir do Request
     */
    public static function fromRequest(array $data, int $empresaId): self
    {
        $dataInicio = isset($data['data_inicio']) 
            ? Carbon::parse($data['data_inicio']) 
            : null;
        
        $dataFim = isset($data['data_fim']) 
            ? Carbon::parse($data['data_fim']) 
            : null;

        return new self(
            empresaId: $empresaId,
            dataInicio: $dataInicio,
            dataFim: $dataFim,
        );
    }

    /**
     * Retorna as datas formatadas para query
     */
    public function getDataInicioFormatted(): ?string
    {
        return $this->dataInicio?->toDateTimeString();
    }

    public function getDataFimFormatted(): ?string
    {
        return $this->dataFim?->toDateTimeString();
    }
}






