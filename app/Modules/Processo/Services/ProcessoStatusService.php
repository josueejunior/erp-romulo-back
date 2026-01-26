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
     * Verifica se o processo deve sugerir mudança para julgamento_habilitacao
     * (após data/hora da sessão pública)
     */
    public function deveSugerirJulgamento(Processo $processo): bool
    {
        if ($processo->status !== 'participacao') {
            return false;
        }

        $dataHoraSessao = Carbon::parse($processo->data_hora_sessao_publica);
        $agora = Carbon::now();

        return $agora->isAfter($dataHoraSessao);
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

        // Regras de transição alinhadas ao fluxo definido
        $transicoesPermitidas = [
            'participacao' => ['julgamento_habilitacao', 'vencido', 'perdido'],
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
     * Sugere próximo status baseado nas regras de negócio
     */
    public function sugerirProximoStatus(Processo $processo): ?string
    {
        // Se está em participação e já passou a data da sessão
        if ($this->deveSugerirJulgamento($processo)) {
            return 'julgamento_habilitacao';
        }

        // Se está em julgamento e todos os itens estão perdidos
        if ($this->deveSugerirPerdido($processo)) {
            return 'perdido';
        }

        return null;
    }

    /**
     * Verifica e atualiza automaticamente os status dos processos
     * Deve ser executado periodicamente (via comando agendado)
     * 
     * @param int|null $empresaId Se fornecido, processa apenas processos desta empresa
     */
    public function verificarEAtualizarStatusAutomaticos(?int $empresaId = null): array
    {
        $resultado = [
            'atualizados' => 0,
            'sugeridos' => 0,
            'erros' => []
        ];

        // Processos em participação que já passaram da sessão pública
        // Usar ProcessoRepository para buscar processos
        $filtrosParticipacao = [
            'empresa_id' => $empresaId,
            'status' => 'participacao',
            'data_hora_sessao_publica_fim' => now(),
        ];
        
        // Buscar modelos Eloquent (necessário para alterar status)
        $processosParticipacao = $this->processoRepository->buscarModelosComFiltros($filtrosParticipacao);

        foreach ($processosParticipacao as $processo) {
            try {
                $result = $this->alterarStatus($processo, 'julgamento_habilitacao', true);
                if ($result['pode']) {
                    $resultado['atualizados']++;
                }
            } catch (\Exception $e) {
                $resultado['erros'][] = "Erro ao atualizar processo {$processo->id}: " . $e->getMessage();
            }
        }

        // Processos em julgamento que devem ser marcados como perdidos
        // Usar ProcessoRepository para buscar processos
        $filtrosJulgamento = [
            'empresa_id' => $empresaId,
            'status' => 'julgamento_habilitacao',
        ];
        
        // Buscar modelos Eloquent (necessário para alterar status)
        $processosJulgamento = $this->processoRepository->buscarModelosComFiltros($filtrosJulgamento);

        foreach ($processosJulgamento as $processo) {
            if ($this->deveSugerirPerdido($processo)) {
                $resultado['sugeridos']++;
                // Não atualiza automaticamente, apenas sugere
                // O usuário deve confirmar a marcação como perdido
            }
        }

        return $resultado;
    }
}




