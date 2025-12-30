<?php

namespace App\Modules\Notification\Services;

use App\Domain\Processo\Repositories\ProcessoRepositoryInterface;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use Carbon\Carbon;

/**
 * Service para gerenciar notificações e alertas do sistema
 */
class NotificationService
{
    public function __construct(
        private ProcessoRepositoryInterface $processoRepository,
        private DocumentoHabilitacaoRepositoryInterface $documentoRepository,
        private AssinaturaRepositoryInterface $assinaturaRepository,
    ) {}

    /**
     * Obter todas as notificações para um usuário/empresa
     */
    public function obterNotificacoes(int $empresaId, ?int $tenantId = null): array
    {
        $notificacoes = [];

        // Notificações de processos
        $notificacoes = array_merge($notificacoes, $this->obterNotificacoesProcessos($empresaId));

        // Notificações de documentos
        $notificacoes = array_merge($notificacoes, $this->obterNotificacoesDocumentos($empresaId));

        // Notificações de assinatura (se tenantId fornecido)
        if ($tenantId) {
            $notificacoes = array_merge($notificacoes, $this->obterNotificacoesAssinatura($tenantId));
        }

        // Ordenar por prioridade e data
        usort($notificacoes, function ($a, $b) {
            $prioridadeOrder = ['alta' => 3, 'media' => 2, 'baixa' => 1];
            $prioridadeDiff = ($prioridadeOrder[$b['prioridade']] ?? 0) - ($prioridadeOrder[$a['prioridade']] ?? 0);
            if ($prioridadeDiff !== 0) {
                return $prioridadeDiff;
            }
            return strtotime($b['data']) - strtotime($a['data']);
        });

        return [
            'notificacoes' => $notificacoes,
            'total' => count($notificacoes),
            'nao_lidas' => count(array_filter($notificacoes, fn($n) => !($n['lida'] ?? false))),
            'alta_prioridade' => count(array_filter($notificacoes, fn($n) => $n['prioridade'] === 'alta')),
        ];
    }

    /**
     * Obter notificações relacionadas a processos
     */
    private function obterNotificacoesProcessos(int $empresaId): array
    {
        $notificacoes = [];
        $hoje = Carbon::now();
        $em3Dias = $hoje->copy()->addDays(3);
        $em7Dias = $hoje->copy()->addDays(7);

        // Processos com sessão pública em até 3 dias
        $processosProximos = $this->processoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'status' => ['participacao', 'julgamento_habilitacao'],
            'data_hora_sessao_publica_inicio' => $hoje,
            'data_hora_sessao_publica_fim' => $em3Dias,
            'per_page' => 100,
        ]);

        foreach ($processosProximos->getCollection() as $processo) {
            if (!$processo->dataHoraSessaoPublica) {
                continue;
            }

            $diasRestantes = $hoje->diffInDays($processo->dataHoraSessaoPublica, false);
            
            if ($diasRestantes < 0) {
                continue; // Já passou
            }

            $prioridade = $diasRestantes <= 1 ? 'alta' : ($diasRestantes <= 3 ? 'media' : 'baixa');
            
            $notificacoes[] = [
                'id' => 'processo_' . $processo->id . '_sessao',
                'tipo' => 'processo',
                'categoria' => 'sessao_publica',
                'titulo' => 'Sessão Pública Próxima',
                'mensagem' => "Processo {$processo->numeroModalidade} tem sessão pública em " . ($diasRestantes === 0 ? 'hoje' : "{$diasRestantes} dia(s)"),
                'prioridade' => $prioridade,
                'data' => $processo->dataHoraSessaoPublica->toDateTimeString(),
                'link' => "/processos/{$processo->id}",
                'processo_id' => $processo->id,
                'lida' => false,
            ];
        }

        // Processos em execução com saldo pendente alto
        $processosExecucao = $this->processoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'status' => 'execucao',
            'per_page' => 50,
        ]);

        foreach ($processosExecucao->getCollection() as $processo) {
            // Calcular saldo pendente (simplificado - pode ser melhorado)
            $saldoPendente = 0;
            foreach ($processo->itens ?? [] as $item) {
                if (in_array($item->statusItem ?? '', ['aceito', 'aceito_habilitado'])) {
                    $saldoPendente += ($item->saldoAberto ?? 0);
                }
            }

            if ($saldoPendente > 10000) { // Acima de R$ 10.000
                $notificacoes[] = [
                    'id' => 'processo_' . $processo->id . '_saldo',
                    'tipo' => 'processo',
                    'categoria' => 'saldo_pendente',
                    'titulo' => 'Saldo Pendente Alto',
                    'mensagem' => "Processo {$processo->numeroModalidade} tem saldo pendente de " . number_format($saldoPendente, 2, ',', '.'),
                    'prioridade' => 'media',
                    'data' => $processo->updatedAt?->toDateTimeString() ?? now()->toDateTimeString(),
                    'link' => "/processos/{$processo->id}",
                    'processo_id' => $processo->id,
                    'lida' => false,
                ];
            }
        }

        return $notificacoes;
    }

    /**
     * Obter notificações relacionadas a documentos
     */
    private function obterNotificacoesDocumentos(int $empresaId): array
    {
        $notificacoes = [];
        $hoje = Carbon::now();
        $em7Dias = $hoje->copy()->addDays(7);
        $em30Dias = $hoje->copy()->addDays(30);

        // Documentos vencendo em até 7 dias
        $documentosUrgentes = $this->documentoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'data_validade_inicio' => $hoje,
            'data_validade_fim' => $em7Dias,
            'per_page' => 100,
        ]);

        foreach ($documentosUrgentes->getCollection() as $documento) {
            if (!$documento->dataValidade) {
                continue;
            }

            $diasRestantes = $hoje->diffInDays($documento->dataValidade, false);
            
            if ($diasRestantes < 0) {
                // Documento vencido
                $notificacoes[] = [
                    'id' => 'documento_' . $documento->id . '_vencido',
                    'tipo' => 'documento',
                    'categoria' => 'vencido',
                    'titulo' => 'Documento Vencido',
                    'mensagem' => "Documento {$documento->tipo} ({$documento->numero}) está vencido há " . abs($diasRestantes) . " dia(s)",
                    'prioridade' => 'alta',
                    'data' => $documento->dataValidade->toDateTimeString(),
                    'link' => "/documentos-habilitacao?vencendo=1",
                    'documento_id' => $documento->id,
                    'lida' => false,
                ];
            } else {
                $prioridade = $diasRestantes <= 3 ? 'alta' : 'media';
                
                $notificacoes[] = [
                    'id' => 'documento_' . $documento->id . '_vencendo',
                    'tipo' => 'documento',
                    'categoria' => 'vencendo',
                    'titulo' => 'Documento Vencendo',
                    'mensagem' => "Documento {$documento->tipo} ({$documento->numero}) vence em {$diasRestantes} dia(s)",
                    'prioridade' => $prioridade,
                    'data' => $documento->dataValidade->toDateTimeString(),
                    'link' => "/documentos-habilitacao?vencendo=1",
                    'documento_id' => $documento->id,
                    'lida' => false,
                ];
            }
        }

        // Documentos vencendo em até 30 dias (baixa prioridade)
        $documentosVencendo = $this->documentoRepository->buscarComFiltros([
            'empresa_id' => $empresaId,
            'data_validade_inicio' => $em7Dias,
            'data_validade_fim' => $em30Dias,
            'per_page' => 50,
        ]);

        foreach ($documentosVencendo->getCollection() as $documento) {
            if (!$documento->dataValidade) {
                continue;
            }

            $diasRestantes = $hoje->diffInDays($documento->dataValidade, false);
            
            $notificacoes[] = [
                'id' => 'documento_' . $documento->id . '_aviso',
                'tipo' => 'documento',
                'categoria' => 'aviso',
                'titulo' => 'Documento Vencendo em Breve',
                'mensagem' => "Documento {$documento->tipo} ({$documento->numero}) vence em {$diasRestantes} dia(s)",
                'prioridade' => 'baixa',
                'data' => $documento->dataValidade->toDateTimeString(),
                'link' => "/documentos-habilitacao",
                'documento_id' => $documento->id,
                'lida' => false,
            ];
        }

        return $notificacoes;
    }

    /**
     * Obter notificações relacionadas a assinatura
     */
    private function obterNotificacoesAssinatura(int $tenantId): array
    {
        $notificacoes = [];
        
        try {
            $assinatura = $this->assinaturaRepository->buscarAssinaturaAtual($tenantId);
            
            if (!$assinatura) {
                return [
                    [
                        'id' => 'assinatura_sem_plano',
                        'tipo' => 'assinatura',
                        'categoria' => 'sem_plano',
                        'titulo' => 'Nenhuma Assinatura Ativa',
                        'mensagem' => 'Você não possui uma assinatura ativa. Contrate um plano para continuar usando o sistema.',
                        'prioridade' => 'alta',
                        'data' => now()->toDateTimeString(),
                        'link' => '/planos',
                        'lida' => false,
                    ],
                ];
            }

            $hoje = Carbon::now();
            $diasRestantes = $hoje->diffInDays($assinatura->dataFim, false);

            if ($diasRestantes < 0) {
                // Assinatura expirada
                $notificacoes[] = [
                    'id' => 'assinatura_expirada',
                    'tipo' => 'assinatura',
                    'categoria' => 'expirada',
                    'titulo' => 'Assinatura Expirada',
                    'mensagem' => 'Sua assinatura expirou há ' . abs($diasRestantes) . ' dia(s). Renove para continuar usando o sistema.',
                    'prioridade' => 'alta',
                    'data' => $assinatura->dataFim->toDateTimeString(),
                    'link' => '/planos',
                    'lida' => false,
                ];
            } elseif ($diasRestantes <= 7) {
                // Assinatura vencendo em até 7 dias
                $notificacoes[] = [
                    'id' => 'assinatura_vencendo',
                    'tipo' => 'assinatura',
                    'categoria' => 'vencendo',
                    'titulo' => 'Assinatura Vencendo',
                    'mensagem' => "Sua assinatura vence em {$diasRestantes} dia(s). Renove para evitar interrupções.",
                    'prioridade' => $diasRestantes <= 3 ? 'alta' : 'media',
                    'data' => $assinatura->dataFim->toDateTimeString(),
                    'link' => '/planos',
                    'lida' => false,
                ];
            } elseif ($diasRestantes <= 30) {
                // Assinatura vencendo em até 30 dias
                $notificacoes[] = [
                    'id' => 'assinatura_aviso',
                    'tipo' => 'assinatura',
                    'categoria' => 'aviso',
                    'titulo' => 'Assinatura Vencendo em Breve',
                    'mensagem' => "Sua assinatura vence em {$diasRestantes} dia(s). Considere renovar.",
                    'prioridade' => 'baixa',
                    'data' => $assinatura->dataFim->toDateTimeString(),
                    'link' => '/planos',
                    'lida' => false,
                ];
            }
        } catch (\Exception $e) {
            // Ignorar erros ao buscar assinatura
        }

        return $notificacoes;
    }
}

