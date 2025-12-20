<?php

namespace App\Services;

use App\Models\Processo;
use App\Models\ProcessoItem;
use App\Services\ProcessoStatusService;
use Carbon\Carbon;

class DisputaService
{
    protected ProcessoStatusService $statusService;

    public function __construct(ProcessoStatusService $statusService)
    {
        $this->statusService = $statusService;
    }

    /**
     * Registra resultados da disputa (valores finais e classificações)
     */
    public function registrarResultados(Processo $processo, array $resultadosItens): Processo
    {
        foreach ($resultadosItens as $resultado) {
            $item = ProcessoItem::find($resultado['item_id']);
            
            if (!$item || $item->processo_id !== $processo->id) {
                continue;
            }

            // Atualizar valores
            if (isset($resultado['valor_final_sessao'])) {
                $item->valor_final_sessao = $resultado['valor_final_sessao'];
            }

            if (isset($resultado['classificacao'])) {
                $item->classificacao = $resultado['classificacao'];
            }

            $item->save();
        }

        // Se passou da data da sessão, sugerir mudança para julgamento
        if ($this->statusService->deveSugerirJulgamento($processo)) {
            // Não muda automaticamente, apenas sugere
            // A mudança deve ser confirmada manualmente
        }

        return $processo->fresh();
    }

    /**
     * Registra informações de julgamento e habilitação por item
     */
    public function registrarJulgamento(
        ProcessoItem $item,
        string $statusItem,
        ?int $classificacao = null,
        ?string $chanceArremate = null,
        ?int $chancePercentual = null,
        ?float $valorNegociado = null,
        ?string $lembretes = null,
        ?string $observacoes = null
    ): ProcessoItem {
        // Validar status do item
        $statusPermitidos = ['aceito', 'aceito_habilitado', 'desclassificado', 'inabilitado'];
        if (!in_array($statusItem, $statusPermitidos)) {
            throw new \InvalidArgumentException("Status de item inválido: {$statusItem}");
        }

        $item->status_item = $statusItem;

        if ($classificacao !== null) {
            $item->classificacao = $classificacao;
        }

        if ($chanceArremate !== null) {
            $item->chance_arremate = $chanceArremate;
        }

        if ($chancePercentual !== null) {
            $item->chance_percentual = $chancePercentual;
        }

        // Valor negociado não apaga o valor anterior, apenas adiciona
        if ($valorNegociado !== null) {
            $item->valor_negociado = $valorNegociado;
        }

        if ($lembretes !== null) {
            $item->lembretes = $lembretes;
        }

        if ($observacoes !== null) {
            $item->observacoes = $observacoes;
        }

        $item->save();

        // Verificar se processo deve mudar de status
        $processo = $item->processo;
        
        // Se todos os itens estão perdidos, sugerir perdido
        if ($this->statusService->deveSugerirPerdido($processo)) {
            // Sugestão será feita pela interface
        }

        return $item->fresh();
    }
}



