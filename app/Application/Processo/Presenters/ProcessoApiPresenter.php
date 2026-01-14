<?php

namespace App\Application\Processo\Presenters;

use App\Modules\Processo\Models\Processo as ProcessoModel;

/**
 * Presenter para serialização de Processo na API
 * 
 * ✅ Responsabilidade única: transformar modelos Eloquent em arrays para resposta JSON
 * ✅ Remove lógica de serialização do Controller
 * ✅ Facilita testes e mudanças de formato
 */
class ProcessoApiPresenter
{
    /**
     * Transforma um modelo Processo em array para resposta da API
     */
    public function present(ProcessoModel $processo): array
    {
        $array = $processo->toArray();
        
        // Incluir dados do órgão se existir
        if ($processo->orgao) {
            $array['orgao'] = [
                'id' => $processo->orgao->id,
                'razao_social' => $processo->orgao->razao_social ?? null,
                'nome_fantasia' => $processo->orgao->nome_fantasia ?? null,
                'cnpj' => $processo->orgao->cnpj ?? null,
            ];
        }
        
        // Incluir dados do setor se existir
        if ($processo->setor) {
            $array['setor'] = [
                'id' => $processo->setor->id,
                'nome' => $processo->setor->nome ?? null,
            ];
        }
        
        // Incluir contadores de relacionamentos se carregados
        if ($processo->relationLoaded('itens')) {
            $array['total_itens'] = $processo->itens->count();
        }
        
        if ($processo->relationLoaded('empenhos')) {
            $array['total_empenhos'] = $processo->empenhos->count();
        }
        
        if ($processo->relationLoaded('documentos')) {
            $array['total_documentos'] = $processo->documentos->count();
        }
        
        return $array;
    }
    
    /**
     * Transforma uma coleção de modelos Processo em array
     */
    public function presentCollection(iterable $processos): array
    {
        return collect($processos)
            ->map(fn($processo) => $this->present($processo))
            ->filter()
            ->values()
            ->all();
    }
}









