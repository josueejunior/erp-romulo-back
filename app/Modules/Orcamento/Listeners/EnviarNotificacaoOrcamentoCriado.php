<?php

namespace App\Modules\Orcamento\Listeners;

use App\Modules\Orcamento\Events\OrcamentoCriado;
use App\Modules\Orcamento\Models\Notificacao;
use App\Models\User;

class EnviarNotificacaoOrcamentoCriado
{
    public function handle(OrcamentoCriado $event)
    {
        $orcamento = $event->orcamento;
        
        // Buscar usuários que devem receber notificação
        // Exemplo: usuários com permissão de "visualizar_orcamentos"
        $usuarios = User::where('empresa_id', $orcamento->empresa_id)
            ->where('ativo', true)
            ->get();

        foreach ($usuarios as $usuario) {
            Notificacao::criar(
                $usuario->id,
                $orcamento->empresa_id,
                'orcamento_criado',
                'Novo Orçamento Criado',
                "Orçamento #{$orcamento->id} criado para o processo #{$orcamento->processo_id} pelo fornecedor {$orcamento->fornecedor?->nome}",
                [
                    'orcamento_id' => $orcamento->id,
                    'processo_id' => $orcamento->processo_id,
                    'fornecedor_id' => $orcamento->fornecedor_id,
                    'valor' => $orcamento->valor_total
                ]
            );
        }
    }
}
