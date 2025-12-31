<?php

namespace App\Modules\Orcamento\Events;

use App\Modules\Orcamento\Models\Orcamento;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrcamentoAtualizado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Orcamento $orcamento;
    public $empresaId;
    public $campo;
    public $valorAnterior;
    public $valorNovo;

    public function __construct(Orcamento $orcamento, $campo, $valorAnterior, $valorNovo)
    {
        $this->orcamento = $orcamento;
        $this->empresaId = $orcamento->empresa_id;
        $this->campo = $campo;
        $this->valorAnterior = $valorAnterior;
        $this->valorNovo = $valorNovo;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('empresa.' . $this->empresaId);
    }

    public function broadcastAs()
    {
        return 'orcamento.atualizado';
    }

    public function broadcastWith()
    {
        return [
            'orcamento_id' => $this->orcamento->id,
            'campo' => $this->campo,
            'valor_anterior' => $this->valorAnterior,
            'valor_novo' => $this->valorNovo,
            'status' => $this->orcamento->status,
            'updated_at' => $this->orcamento->updated_at
        ];
    }
}
