<?php

namespace App\Services;

use App\Models\Processo;
use App\Models\ProcessoItem;
use Carbon\Carbon;

class ProcessoStatusService
{
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

        // Regras de transição
        $transicoesPermitidas = [
            'participacao' => ['julgamento_habilitacao', 'vencido', 'perdido'],
            'julgamento_habilitacao' => ['vencido', 'perdido', 'execucao', 'arquivado'],
            'vencido' => ['execucao'],
            'perdido' => ['arquivado'],
            'execucao' => ['pagamento'], // Execução pode ir para pagamento
            'pagamento' => ['encerramento'], // Pagamento pode ir para encerramento
            'encerramento' => ['arquivado'], // Encerramento pode ser arquivado
            'arquivado' => [], // Não pode mudar de arquivado
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
                if (!$this->deveSugerirPerdido($processo)) {
                    return [
                        'pode' => false,
                        'motivo' => 'Não é possível marcar como perdido: há itens aceitos ou em análise'
                    ];
                }
                break;

            case 'execucao':
                if ($statusAtual !== 'vencido') {
                    return [
                        'pode' => false,
                        'motivo' => 'Apenas processos vencidos podem entrar em execução'
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
        // Sempre filtrar por empresa_id não nulo para garantir isolamento
        $queryParticipacao = Processo::where('status', 'participacao')
            ->where('data_hora_sessao_publica', '<=', now())
            ->whereNotNull('empresa_id');
        
        if ($empresaId) {
            $queryParticipacao->where('empresa_id', $empresaId);
        }
        
        $processosParticipacao = $queryParticipacao->get();

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
        // Sempre filtrar por empresa_id não nulo para garantir isolamento
        $queryJulgamento = Processo::where('status', 'julgamento_habilitacao')
            ->whereNotNull('empresa_id');
        
        if ($empresaId) {
            $queryJulgamento->where('empresa_id', $empresaId);
        }
        
        $processosJulgamento = $queryJulgamento->get();

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




