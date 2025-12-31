<?php

namespace App\Modules\Orcamento\Domain\Aggregates;

use App\Modules\Orcamento\Domain\ValueObjects\TipoNotificacao;
use App\Modules\Orcamento\Domain\ValueObjects\MensagemNotificacao;
use Carbon\Carbon;

class NotificacaoAggregate
{
    private int $id;
    private int $usuarioId;
    private int $empresaId;
    private TipoNotificacao $tipo;
    private string $titulo;
    private MensagemNotificacao $mensagem;
    private ?int $orcamentoId;
    private ?int $processoId;
    private bool $lido;
    private ?Carbon $lidoEm;
    private array $dadosAdicionais;
    private Carbon $criadoEm;

    private function __construct(
        int $usuarioId,
        int $empresaId,
        TipoNotificacao $tipo,
        string $titulo,
        MensagemNotificacao $mensagem,
        ?int $orcamentoId = null,
        ?int $processoId = null,
        array $dadosAdicionais = []
    ) {
        $this->usuarioId = $usuarioId;
        $this->empresaId = $empresaId;
        $this->tipo = $tipo;
        $this->titulo = $titulo;
        $this->mensagem = $mensagem;
        $this->orcamentoId = $orcamentoId;
        $this->processoId = $processoId;
        $this->lido = false;
        $this->lidoEm = null;
        $this->dadosAdicionais = $dadosAdicionais;
        $this->criadoEm = Carbon::now();
    }

    public static function criar(
        int $usuarioId,
        int $empresaId,
        TipoNotificacao $tipo,
        string $titulo,
        MensagemNotificacao $mensagem,
        ?int $orcamentoId = null,
        ?int $processoId = null,
        array $dadosAdicionais = []
    ): self {
        return new self($usuarioId, $empresaId, $tipo, $titulo, $mensagem, $orcamentoId, $processoId, $dadosAdicionais);
    }

    public function marcarComoLida(): void
    {
        $this->lido = true;
        $this->lidoEm = Carbon::now();
    }

    public function marcarComoNaoLida(): void
    {
        $this->lido = false;
        $this->lidoEm = null;
    }

    // ====== GETTERS ======

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsuarioId(): int
    {
        return $this->usuarioId;
    }

    public function getEmpresaId(): int
    {
        return $this->empresaId;
    }

    public function getTipo(): TipoNotificacao
    {
        return $this->tipo;
    }

    public function getTitulo(): string
    {
        return $this->titulo;
    }

    public function getMensagem(): MensagemNotificacao
    {
        return $this->mensagem;
    }

    public function getOrcamentoId(): ?int
    {
        return $this->orcamentoId;
    }

    public function getProcessoId(): ?int
    {
        return $this->processoId;
    }

    public function isLido(): bool
    {
        return $this->lido;
    }

    public function getLidoEm(): ?Carbon
    {
        return $this->lidoEm;
    }

    public function getDadosAdicionais(): array
    {
        return $this->dadosAdicionais;
    }

    public function getCriadoEm(): Carbon
    {
        return $this->criadoEm;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id ?? null,
            'usuario_id' => $this->usuarioId,
            'empresa_id' => $this->empresaId,
            'tipo' => $this->tipo->getValue(),
            'titulo' => $this->titulo,
            'mensagem' => $this->mensagem->getValue(),
            'orcamento_id' => $this->orcamentoId,
            'processo_id' => $this->processoId,
            'lido' => $this->lido,
            'lido_em' => $this->lidoEm?->toIso8601String(),
            'dados_adicionais' => $this->dadosAdicionais,
            'criado_em' => $this->criadoEm->toIso8601String()
        ];
    }
}
