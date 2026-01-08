<?php

declare(strict_types=1);

namespace App\Application\Calendario\DTOs;

use Carbon\Carbon;

/**
 * DTO para buscar calendário de disputas
 */
final class BuscarCalendarioDisputasDTO
{
    public function __construct(
        public readonly int $empresaId,
        public readonly Carbon $dataInicio,
        public readonly Carbon $dataFim,
    ) {}

    /**
     * Cria DTO a partir do Request
     */
    public static function fromRequest(array $data, int $empresaId): self
    {
        // Se não especificado, buscar disputas dos próximos 60 dias
        $dataInicio = isset($data['data_inicio']) 
            ? Carbon::parse($data['data_inicio']) 
            : Carbon::now()->startOfDay();
        
        $dataFim = isset($data['data_fim']) 
            ? Carbon::parse($data['data_fim']) 
            : Carbon::now()->addDays(60)->endOfDay();

        return new self(
            empresaId: $empresaId,
            dataInicio: $dataInicio,
            dataFim: $dataFim,
        );
    }

    /**
     * Retorna as datas formatadas para query
     */
    public function getDataInicioFormatted(): string
    {
        return $this->dataInicio->toDateTimeString();
    }

    public function getDataFimFormatted(): string
    {
        return $this->dataFim->toDateTimeString();
    }

    /**
     * Retorna chave de cache
     */
    public function getCacheKey(int $tenantId): string
    {
        $mes = $this->dataInicio->month;
        $ano = $this->dataInicio->year;
        return "calendario_disputas_{$tenantId}_{$this->empresaId}_{$mes}_{$ano}";
    }
}

