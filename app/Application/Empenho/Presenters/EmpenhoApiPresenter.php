<?php

namespace App\Application\Empenho\Presenters;

use App\Modules\Empenho\Models\Empenho as EmpenhoModel;

/**
 * Presenter para serialização de Empenho na API
 * 
 * ✅ Responsabilidade única: transformar modelos Eloquent em arrays para resposta JSON
 * ✅ Remove lógica de serialização do Controller
 * ✅ Facilita testes e mudanças de formato
 */
class EmpenhoApiPresenter
{
    /**
     * Transforma um modelo Empenho em array para resposta da API
     */
    public function present(EmpenhoModel $empenho): array
    {
        $array = $empenho->toArray();
        
        // Incluir dados do processo se existir
        if ($empenho->processo) {
            $array['processo'] = [
                'id' => $empenho->processo->id,
                'numero' => $empenho->processo->numero ?? null,
                'numero_modalidade' => $empenho->processo->numero_modalidade ?? null,
                'objeto' => $empenho->processo->objeto ?? null,
                'objeto_resumido' => $empenho->processo->objeto_resumido ?? null,
                'modalidade' => $empenho->processo->modalidade ?? null,
            ];
            // Garantir que processo_id está presente
            if (!isset($array['processo_id'])) {
                $array['processo_id'] = $empenho->processo->id;
            }
        }
        
        // Incluir dados do contrato se existir
        if ($empenho->contrato) {
            $array['contrato'] = [
                'id' => $empenho->contrato->id,
                'numero' => $empenho->contrato->numero ?? null,
            ];
        }
        
        // Incluir dados da autorização de fornecimento se existir
        if ($empenho->autorizacaoFornecimento) {
            $array['autorizacao_fornecimento'] = [
                'id' => $empenho->autorizacaoFornecimento->id,
                'numero' => $empenho->autorizacaoFornecimento->numero ?? null,
            ];
        }
        
        return $array;
    }
    
    /**
     * Transforma uma coleção de modelos Empenho em array
     */
    public function presentCollection(iterable $empenhos): array
    {
        return collect($empenhos)
            ->map(fn($empenho) => $this->present($empenho))
            ->filter()
            ->values()
            ->all();
    }
}






