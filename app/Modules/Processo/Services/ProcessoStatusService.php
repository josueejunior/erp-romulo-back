<?php

namespace App\Modules\Processo\Services;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Modules\Processo\Models\Processo;
use App\Modules\Processo\Models\ProcessoItem;
use Carbon\Carbon;

class ProcessoStatusService
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
    ) {}

    /**
     * Retorna a data/hora efetiva da sessão (fim do intervalo de disputa).
     * Usa o horário informado no processo; se só a data (00:00), considera fim do mesmo dia (23:59).
     */
    public function getDataHoraSessaoEfetiva(Processo $processo): ?Carbon
    {
        if (!$processo->data_hora_sessao_publica) {
            return null;
        }
        $dt = Carbon::parse($processo->data_hora_sessao_publica);
        $h = (int) $dt->format('H');
        $i = (int) $dt->format('i');
        $s = (int) $dt->format('s');
        if ($h === 0 && $i === 0 && $s === 0) {
            return $dt->copy()->endOfDay();
        }
        return $dt;
    }

    /**
     * Retorna a data/hora de início do intervalo de disputa (quando passa a ficar "em disputa").
     */
    public function getDataHoraInicioDisputa(Processo $processo): ?Carbon
    {
        if (!$processo->data_hora_inicio_disputa) {
            return null;
        }
        return Carbon::parse($processo->data_hora_inicio_disputa);
    }

    /**
     * Retorna o status sugerido pelo período: antes do início = participação (em preparação),
     * entre início e fim = em disputa, após o fim = julgamento.
     * Se não houver data da sessão (fim), retorna null (sem sugestão automática).
     */
    public function getStatusSugeridoPorPeriodo(Processo $processo): ?string
    {
        $fim = $this->getDataHoraSessaoEfetiva($processo);
        if (!$fim) {
            return null;
        }

        $agora = Carbon::now();
        $inicio = $this->getDataHoraInicioDisputa($processo);

        if ($inicio) {
            if ($agora->isBefore($inicio)) {
                return 'participacao'; // em preparação
            }
            if ($agora->isAfter($fim)) {
                return 'julgamento_habilitacao'; // em julgamento
            }
            return 'em_disputa'; // no intervalo = em disputa
        }

        // Retrocompat: sem início definido → antes do fim = participação, depois = julgamento
        return $agora->isAfter($fim) ? 'julgamento_habilitacao' : 'participacao';
    }

    /**
     * Verifica se o processo deve sugerir mudança para julgamento_habilitacao
     * (após o fim do intervalo de disputa / data da sessão).
     */
    public function deveSugerirJulgamento(Processo $processo): bool
    {
        $statusSugerido = $this->getStatusSugeridoPorPeriodo($processo);
        return $statusSugerido === 'julgamento_habilitacao'
            && in_array($processo->status, ['participacao', 'em_disputa']);
    }

    /**
     * Verifica se o processo deve sugerir status perdido
     * (todos os itens desclassificados/inabilitados)
     */
    public function deveSugerirPerdido(Processo $processo): bool
    {
        if ($processo->status !== 'julgamento_habilitacao') {
            return false;
        }

        $itens = $processo->itens;
        
        if ($itens->isEmpty()) {
            return false;
        }

        // Todos os itens devem estar desclassificados ou inabilitados
        $todosPerdidos = $itens->every(function ($item) {
            return in_array($item->status_item, ['desclassificado', 'inabilitado']);
        });

        return $todosPerdidos;
    }

    /**
     * Verifica se o processo tem pelo menos um item aceito
     */
    public function temItemAceito(Processo $processo): bool
    {
        return $processo->itens()
            ->whereIn('status_item', ['aceito', 'aceito_habilitado'])
            ->exists();
    }

    /**
     * Valida transição de status
     */
    public function podeAlterarStatus(Processo $processo, string $novoStatus): array
    {
        $statusAtual = $processo->status;
        $pode = false;
        $motivo = '';

        // Regras de transição: participação → em disputa → julgamento → ...
        $transicoesPermitidas = [
            'participacao' => ['em_disputa', 'julgamento_habilitacao', 'vencido', 'perdido'],
            'em_disputa' => ['julgamento_habilitacao', 'vencido', 'perdido'],
            'julgamento_habilitacao' => ['vencido', 'perdido', 'execucao'],
            'vencido' => ['execucao'],
            'execucao' => ['pagamento'],
            'pagamento' => ['encerramento'],
            'encerramento' => ['arquivado'],
            'perdido' => ['arquivado'],
            'arquivado' => [],
        ];

        // Verificar se a transição é permitida
        if (!in_array($novoStatus, $transicoesPermitidas[$statusAtual] ?? [])) {
            return [
                'pode' => false,
                'motivo' => "Não é possível alterar o status de '{$statusAtual}' para '{$novoStatus}'"
            ];
        }

        // Validações específicas
        switch ($novoStatus) {
            case 'perdido':
                // 🔥 CORREÇÃO: Permitir marcar como perdido se não houver itens
                // O método deveSugerirPerdido retornava false se empty, bloqueando a ação manual
                if ($processo->itens->isEmpty()) {
                     return ['pode' => true, 'motivo' => ''];
                }

                // Se houver itens, valida se todos estão "perdidos" (desclassificados ou inabilitados)
                // Usamos a lógica direta aqui em vez de deveSugerirPerdido para não restringir ao status 'julgamento_habilitacao'
                $todosPerdidos = $processo->itens->every(function ($item) {
                    return in_array($item->status_item, ['desclassificado', 'inabilitado']);
                });

                if (!$todosPerdidos) {
                    return [
                        'pode' => false,
                        'motivo' => 'Não é possível marcar como perdido: há itens aceitos ou em análise. Desclassifique-os primeiro.'
                    ];
                }
                break;

            case 'execucao':
                // Só entra em execução vindo de vencido ou de julgamento_habilitacao com item aceito
                if ($statusAtual === 'julgamento_habilitacao') {
                    if (!$this->temItemAceito($processo)) {
                        return [
                            'pode' => false,
                            'motivo' => 'Não é possível entrar em execução: nenhum item foi aceito'
                        ];
                    }
                } elseif ($statusAtual !== 'vencido') {
                    return [
                        'pode' => false,
                        'motivo' => 'Apenas processos vencidos ou em julgamento com item aceito podem entrar em execução'
                    ];
                }
                break;

            case 'pagamento':
                if ($statusAtual !== 'execucao') {
                    return [
                        'pode' => false,
                        'motivo' => 'Apenas processos em execução podem entrar em pagamento'
                    ];
                }
                break;

            case 'encerramento':
                if ($statusAtual !== 'pagamento') {
                    return [
                        'pode' => false,
                        'motivo' => 'Apenas processos em pagamento podem ser encerrados'
                    ];
                }
                break;

            case 'arquivado':
                if (!in_array($statusAtual, ['perdido', 'encerramento'])) {
                    return [
                        'pode' => false,
                        'motivo' => 'Apenas processos perdidos ou encerrados podem ser arquivados'
                    ];
                }
                break;
        }

        return [
            'pode' => true,
            'motivo' => ''
        ];
    }

    /**
     * Altera o status do processo com validações
     */
    public function alterarStatus(Processo $processo, string $novoStatus, bool $forcar = false): array
    {
        // Se não forçar, validar transição
        if (!$forcar) {
            $validacao = $this->podeAlterarStatus($processo, $novoStatus);
            if (!$validacao['pode']) {
                return $validacao;
            }
        }

        $processo->status = $novoStatus;
        
        // Se marcar como perdido, arquivar automaticamente
        if ($novoStatus === 'perdido') {
            $processo->status = 'arquivado';
            $processo->data_arquivamento = now();
        }
        
        $processo->save();

        return [
            'pode' => true,
            'motivo' => 'Status alterado com sucesso',
            'processo' => $processo
        ];
    }

    /**
     * Sugere próximo status baseado no período (preparação / disputa / julgamento) ou em regras de negócio
     */
    public function sugerirProximoStatus(Processo $processo): ?string
    {
        $statusPorPeriodo = $this->getStatusSugeridoPorPeriodo($processo);
        if ($statusPorPeriodo !== null && $statusPorPeriodo !== $processo->status) {
            return $statusPorPeriodo;
        }

        if ($this->deveSugerirPerdido($processo)) {
            return 'perdido';
        }

        return null;
    }

    /**
     * Verifica e atualiza automaticamente os status dos processos pelo período
     * (em preparação → em disputa → em julgamento). Deve ser executado periodicamente (comando agendado).
     *
     * @param int|null $empresaId Se fornecido, processa apenas processos desta empresa
     */
    public function verificarEAtualizarStatusAutomaticos(?int $empresaId = null): array
    {
        $resultado = [
            'atualizados' => 0,
            'sugeridos' => 0,
            'erros' => [],
        ];

        $filtros = array_filter(['empresa_id' => $empresaId]);
        $statusParaProcessar = ['participacao', 'em_disputa'];
        $processos = $this->processoRepository->buscarModelosComFiltros(
            array_merge($filtros, [
                'status' => $statusParaProcessar,
                'com_sessao_definida' => true,
            ])
        );

        foreach ($processos as $processo) {
            $sugerido = $this->getStatusSugeridoPorPeriodo($processo);
            if ($sugerido === null || $sugerido === $processo->status) {
                continue;
            }
            try {
                $result = $this->alterarStatus($processo, $sugerido, true);
                if ($result['pode']) {
                    $resultado['atualizados']++;
                }
            } catch (\Exception $e) {
                $resultado['erros'][] = "Erro ao atualizar processo {$processo->id}: " . $e->getMessage();
            }
        }

        $filtrosJulgamento = array_merge($filtros, ['status' => 'julgamento_habilitacao']);
        $processosJulgamento = $this->processoRepository->buscarModelosComFiltros($filtrosJulgamento);
        foreach ($processosJulgamento as $processo) {
            if ($this->deveSugerirPerdido($processo)) {
                $resultado['sugeridos']++;
            }
        }

        return $resultado;
    }
}




