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
     * Obter notificação por ID com validação de ownership
     * 
     * ✅ DDD: Valida ownership (regra de domínio)
     */
    public function obterPorId(int $notificacaoId, int $usuarioId, int $empresaId): ?NotificacaoAggregate
    {
        $notificacao = $this->repository->obter($notificacaoId);
        
        if (!$notificacao) {
            return null;
        }

        // Validar ownership (regra de domínio)
        if ($notificacao->getUsuarioId() !== $usuarioId || $notificacao->getEmpresaId() !== $empresaId) {
            return null;
        }

        return $notificacao;
    }

    /**
     * Obter notificações não lidas de um usuário
     * 
     * ✅ DDD: Retorna Collection para melhor performance e flexibilidade
     */
    public function obterNaoLidas(int $usuarioId, int $empresaId): \Illuminate\Support\Collection
    {
        return \Illuminate\Support\Collection::make(
            $this->repository->obterPorUsuario($usuarioId, $empresaId, true)
        );
    }

    /**
     * Obter todas as notificações de um usuário
     * 
     * ✅ DDD: Retorna Collection para melhor performance e flexibilidade
     */
    public function obterTodas(int $usuarioId, int $empresaId): \Illuminate\Support\Collection
    {
        return \Illuminate\Support\Collection::make(
            $this->repository->obterPorUsuario($usuarioId, $empresaId, false)
        );
    }

    /**
     * Obter notificações de um processo
     * 
     * ✅ DDD: Retorna Collection
     */
    public function obterPorProcesso(int $processoId, int $empresaId): \Illuminate\Support\Collection
    {
        return \Illuminate\Support\Collection::make(
            $this->repository->obterPorProcesso($processoId, $empresaId)
        );
    }

    /**
     * Marcar notificação como lida
     * 
     * ✅ DDD: Valida ownership antes de marcar
     */
    public function marcarComoLida(int $notificacaoId, int $usuarioId, int $empresaId): bool
    {
        // Validar ownership (regra de domínio)
        $notificacao = $this->obterPorId($notificacaoId, $usuarioId, $empresaId);
        
        if (!$notificacao) {
            return false;
        }

        // Marcar como lida (regra de domínio)
        $notificacao->marcarComoLida();
        
        // Persistir
        $this->repository->salvar($notificacao);
        
        return true;
    }

    /**
     * Marcar múltiplas como lidas
     * 
     * ✅ DDD: Valida ownership de cada notificação
     */
    public function marcarMultiplasComoLidas(array $notificacaoIds, int $usuarioId, int $empresaId): int
    {
        $total = 0;
        foreach ($notificacaoIds as $id) {
            if ($this->marcarComoLida($id, $usuarioId, $empresaId)) {
                $total++;
            }
        }
        return $total;
    }

    /**
     * Deletar notificação
     * 
     * ✅ DDD: Valida ownership antes de deletar
     */
    public function deletar(int $notificacaoId, int $usuarioId, int $empresaId): bool
    {
        // Validar ownership (regra de domínio)
        $notificacao = $this->obterPorId($notificacaoId, $usuarioId, $empresaId);
        
        if (!$notificacao) {
            return false;
        }

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
     * 
     * ✅ DDD: Usa Collection ao invés de count() em array
     */
    public function contarNaoLidas(int $usuarioId, int $empresaId): int
    {
        return $this->obterNaoLidas($usuarioId, $empresaId)->count();
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
