<?php

namespace App\Modules\Orcamento\Domain\Services;

use App\Modules\Orcamento\Domain\Repositories\NotificacaoRepositoryInterface;
use App\Modules\Orcamento\Domain\Aggregates\NotificacaoAggregate;
use App\Modules\Orcamento\Domain\ValueObjects\TipoNotificacao;
use App\Modules\Orcamento\Domain\ValueObjects\MensagemNotificacao;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;

class NotificacaoDomainService
{
    private NotificacaoRepositoryInterface $repository;

    public function __construct(NotificacaoRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Criar e salvar notificação
     */
    public function criar(
        int $usuarioId,
        int $empresaId,
        string $tipo,
        string $titulo,
        string $mensagem,
        ?int $orcamentoId = null,
        ?int $processoId = null,
        array $dadosAdicionais = []
    ): NotificacaoAggregate {
        $tipoNotificacao = new TipoNotificacao($tipo);
        $mensagemNotificacao = new MensagemNotificacao($mensagem);

        $notificacao = NotificacaoAggregate::criar(
            $usuarioId,
            $empresaId,
            $tipoNotificacao,
            $titulo,
            $mensagemNotificacao,
            $orcamentoId,
            $processoId,
            $dadosAdicionais
        );

        return $this->repository->salvar($notificacao);
    }

    /**
     * Obter notificações não lidas de um usuário
     */
    public function obterNaoLidas(int $usuarioId, int $empresaId): array
    {
        return $this->repository->obterPorUsuario($usuarioId, $empresaId, true);
    }

    /**
     * Obter todas as notificações de um usuário
     */
    public function obterTodas(int $usuarioId, int $empresaId): array
    {
        return $this->repository->obterPorUsuario($usuarioId, $empresaId, false);
    }

    /**
     * Obter notificações de um processo
     */
    public function obterPorProcesso(int $processoId, int $empresaId): array
    {
        return $this->repository->obterPorProcesso($processoId, $empresaId);
    }

    /**
     * Marcar notificação como lida
     */
    public function marcarComoLida(int $notificacaoId): bool
    {
        return $this->repository->marcarComoLida($notificacaoId);
    }

    /**
     * Marcar múltiplas como lidas
     */
    public function marcarMultiplasComoLidas(array $notificacaoIds): int
    {
        $total = 0;
        foreach ($notificacaoIds as $id) {
            if ($this->repository->marcarComoLida($id)) {
                $total++;
            }
        }
        return $total;
    }

    /**
     * Deletar notificação
     */
    public function deletar(int $notificacaoId): bool
    {
        return $this->repository->deletar($notificacaoId);
    }

    /**
     * Limpar notificações antigas
     */
    public function limparAntigos(int $diasRetencao = 30): int
    {
        return $this->repository->deletarAntigos($diasRetencao);
    }

    /**
     * Contar notificações não lidas
     */
    public function contarNaoLidas(int $usuarioId, int $empresaId): int
    {
        return count($this->obterNaoLidas($usuarioId, $empresaId));
    }

    /**
     * Notificar múltiplos usuários
     */
    public function notificarMultiplos(
        array $usuarioIds,
        int $empresaId,
        string $tipo,
        string $titulo,
        string $mensagem,
        ?int $orcamentoId = null,
        ?int $processoId = null,
        array $dadosAdicionais = []
    ): int {
        $total = 0;
        foreach ($usuarioIds as $usuarioId) {
            try {
                $this->criar(
                    $usuarioId,
                    $empresaId,
                    $tipo,
                    $titulo,
                    $mensagem,
                    $orcamentoId,
                    $processoId,
                    $dadosAdicionais
                );
                $total++;
            } catch (\Exception $e) {
                \Log::error("Erro ao notificar usuário {$usuarioId}: " . $e->getMessage());
            }
        }
        return $total;
    }
}
