<?php

declare(strict_types=1);

namespace App\Application\Calendario\UseCases;

use App\Application\Calendario\DTOs\BuscarCalendarioJulgamentoDTO;
use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Use Case para buscar calendário de julgamento
 * 
 * Fluxo:
 * 1. Buscar processos em julgamento/habilitação
 * 2. Formatar com itens e lembretes
 * 3. Retornar Collection formatada
 */
final class BuscarCalendarioJulgamentoUseCase
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Executa o use case
     */
    public function executar(BuscarCalendarioJulgamentoDTO $dto): Collection
    {
        Log::debug('BuscarCalendarioJulgamentoUseCase::executar - Iniciando', [
            'empresa_id' => $dto->empresaId,
            'data_inicio' => $dto->getDataInicioFormatted(),
            'data_fim' => $dto->getDataFimFormatted(),
        ]);

        $dataInicio = $dto->dataInicio ?? Carbon::now();
        $dataFim = $dto->dataFim ?? Carbon::now()->addDays(30);

        $filtros = [
            'empresa_id' => $dto->empresaId,
            'status' => 'julgamento_habilitacao',
            'data_hora_sessao_publica_inicio' => $dataInicio->toDateTimeString(),
            'data_hora_sessao_publica_fim' => $dataFim->toDateTimeString(),
        ];

        $with = [
            'orgao',
            'setor',
            'itens' => function ($query) {
                // Incluir todos os itens do processo em julgamento
            }
        ];

        $processos = $this->processoRepository->buscarModelosComFiltros($filtros, $with);

        Log::debug('BuscarCalendarioJulgamentoUseCase::executar - Processos encontrados', [
            'count' => $processos->count(),
        ]);

        return $processos->map(function ($processo) {
            return $this->formatarProcessoJulgamento($processo);
        });
    }

    /**
     * Formata processo para calendário de julgamento
     */
    private function formatarProcessoJulgamento($processo): array
    {
        // Incluir todos os itens do processo
        $itensComLembrete = $processo->itens->map(function ($item) {
            return [
                'numero_item' => $item->numero_item,
                'lembrete' => $item->lembretes,
                'classificacao' => $item->classificacao,
                'status_item' => $item->status_item,
                'chance_arremate' => $item->chance_arremate,
                'tem_chance' => $item->tem_chance ?? true,
                'chance_percentual' => $item->chance_percentual,
            ];
        });

        return [
            'id' => $processo->id,
            'identificador' => $processo->identificador,
            'modalidade' => $processo->modalidade,
            'numero_modalidade' => $processo->numero_modalidade,
            'orgao' => $processo->orgao?->razao_social,
            'uasg' => $processo->orgao?->uasg,
            'setor' => $processo->setor?->nome,
            'data_hora_sessao_publica' => $processo->data_hora_sessao_publica,
            'objeto_resumido' => $processo->objeto_resumido,
            'itens_com_lembrete' => $itensComLembrete,
            'total_itens' => $processo->itens->count(),
            'itens_aceitos' => $processo->itens()->whereIn('status_item', ['aceito', 'aceito_habilitado'])->count(),
            'itens_desclassificados' => $processo->itens()->whereIn('status_item', ['desclassificado', 'inabilitado'])->count(),
        ];
    }
}

