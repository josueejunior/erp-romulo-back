<?php

declare(strict_types=1);

namespace App\Application\Calendario\Presenters;

use App\Application\Calendario\DTOs\CalendarioEventoDTO;
use Illuminate\Support\Collection;

/**
 * Presenter para formatação de saída do Calendário
 * 
 * Transforma dados do domínio em formato de API
 */
final class CalendarioPresenter
{
    /**
     * Formata lista de disputas para resposta da API
     * 
     * @param Collection<CalendarioEventoDTO> $eventos
     */
    public static function formatarDisputas(Collection $eventos): array
    {
        return [
            'data' => $eventos->map(fn(CalendarioEventoDTO $e) => $e->toArray())->values()->all(),
            'total' => $eventos->count(),
            'meta' => [
                'tipo' => 'disputas',
                'gerado_em' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Formata lista de disputas para resposta da API (de cache)
     */
    public static function formatarDisputasFromCache(array $data): array
    {
        return [
            'data' => $data,
            'total' => count($data),
            'meta' => [
                'tipo' => 'disputas',
                'from_cache' => true,
                'gerado_em' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Formata lista de julgamentos para resposta da API
     */
    public static function formatarJulgamentos(Collection $processos): array
    {
        return [
            'data' => $processos->values()->all(),
            'total' => $processos->count(),
            'meta' => [
                'tipo' => 'julgamento',
                'gerado_em' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Formata avisos urgentes para resposta da API
     */
    public static function formatarAvisosUrgentes(array $avisos): array
    {
        return [
            'data' => $avisos,
            'total' => count($avisos),
            'meta' => [
                'tipo' => 'avisos_urgentes',
                'gerado_em' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Formata erro de acesso ao plano
     */
    public static function erroAcessoPlano(): array
    {
        return [
            'message' => 'O calendário não está disponível no seu plano. Faça upgrade para o plano Profissional ou superior.',
            'code' => 'PLANO_INSUFICIENTE',
            'action' => 'upgrade',
        ];
    }
}



