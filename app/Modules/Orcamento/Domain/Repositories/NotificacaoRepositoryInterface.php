<?php

namespace App\Modules\Orcamento\Domain\Repositories;

use App\Modules\Orcamento\Domain\Aggregates\NotificacaoAggregate;

interface NotificacaoRepositoryInterface
{
    public function salvar(NotificacaoAggregate $notificacao): NotificacaoAggregate;

    public function obter(int $id): ?NotificacaoAggregate;

    public function obterPorUsuario(int $usuarioId, int $empresaId, bool $apenasNaoLidas = false): array;

    public function obterPorProcesso(int $processoId, int $empresaId): array;

    public function marcarComoLida(int $notificacaoId): bool;

    public function deletar(int $notificacaoId): bool;

    public function deletarAntigos(int $diasRetencao = 30): int;
}
