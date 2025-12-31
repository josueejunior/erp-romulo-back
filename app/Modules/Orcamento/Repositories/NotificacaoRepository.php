<?php

namespace App\Modules\Orcamento\Repositories;

use App\Modules\Orcamento\Domain\Repositories\NotificacaoRepositoryInterface;
use App\Modules\Orcamento\Domain\Aggregates\NotificacaoAggregate;
use App\Modules\Orcamento\Domain\ValueObjects\TipoNotificacao;
use App\Modules\Orcamento\Domain\ValueObjects\MensagemNotificacao;
use App\Modules\Orcamento\Models\Notificacao;

class NotificacaoRepository implements NotificacaoRepositoryInterface
{
    public function salvar(NotificacaoAggregate $notificacao): NotificacaoAggregate
    {
        $dados = $notificacao->toArray();

        $model = Notificacao::create([
            'usuario_id' => $dados['usuario_id'],
            'empresa_id' => $dados['empresa_id'],
            'tipo' => $dados['tipo'],
            'titulo' => $dados['titulo'],
            'mensagem' => $dados['mensagem'],
            'orcamento_id' => $dados['orcamento_id'],
            'processo_id' => $dados['processo_id'],
            'lido' => $dados['lido'],
            'lido_em' => $dados['lido_em'],
            'dados_adicionais' => $dados['dados_adicionais']
        ]);

        return $this->converterParaAggregate($model);
    }

    public function obter(int $id): ?NotificacaoAggregate
    {
        $model = Notificacao::find($id);
        return $model ? $this->converterParaAggregate($model) : null;
    }

    public function obterPorUsuario(int $usuarioId, int $empresaId, bool $apenasNaoLidas = false): array
    {
        $query = Notificacao::where('usuario_id', $usuarioId)
            ->where('empresa_id', $empresaId);

        if ($apenasNaoLidas) {
            $query->where('lido', false);
        }

        return $query->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($model) => $this->converterParaAggregate($model))
            ->toArray();
    }

    public function obterPorProcesso(int $processoId, int $empresaId): array
    {
        return Notificacao::where('processo_id', $processoId)
            ->where('empresa_id', $empresaId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($model) => $this->converterParaAggregate($model))
            ->toArray();
    }

    public function marcarComoLida(int $notificacaoId): bool
    {
        return Notificacao::where('id', $notificacaoId)
            ->update([
                'lido' => true,
                'lido_em' => now()
            ]) > 0;
    }

    public function deletar(int $notificacaoId): bool
    {
        return Notificacao::destroy($notificacaoId) > 0;
    }

    public function deletarAntigos(int $diasRetencao = 30): int
    {
        return Notificacao::where('created_at', '<', now()->subDays($diasRetencao))
            ->delete();
    }

    private function converterParaAggregate(Notificacao $model): NotificacaoAggregate
    {
        $aggregate = NotificacaoAggregate::criar(
            $model->usuario_id,
            $model->empresa_id,
            new TipoNotificacao($model->tipo),
            $model->titulo,
            new MensagemNotificacao($model->mensagem),
            $model->orcamento_id,
            $model->processo_id,
            $model->dados_adicionais ?? []
        );

        return $aggregate;
    }
}
