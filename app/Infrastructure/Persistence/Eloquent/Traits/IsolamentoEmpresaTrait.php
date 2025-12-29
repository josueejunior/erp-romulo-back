<?php

namespace App\Infrastructure\Persistence\Eloquent\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Trait para garantir isolamento por empresa_id em repositories
 * 
 * Aplica o mesmo padrão usado em FornecedorRepository para todos os repositories
 * que precisam filtrar por empresa_id.
 */
trait IsolamentoEmpresaTrait
{
    /**
     * Aplica filtro de empresa_id em uma query, removendo Global Scope para evitar duplicação
     * 
     * @param string $modelClass Classe do modelo Eloquent
     * @param array $filtros Filtros incluindo empresa_id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function aplicarFiltroEmpresa(string $modelClass, array $filtros)
    {
        // CRÍTICO: Sempre filtrar por empresa_id
        if (!isset($filtros['empresa_id']) || empty($filtros['empresa_id'])) {
            Log::warning(static::class . '->buscarComFiltros() chamado sem empresa_id', [
                'filtros' => $filtros,
            ]);
            throw new \InvalidArgumentException('empresa_id é obrigatório nos filtros');
        }

        // IMPORTANTE: Remover Global Scope temporariamente para evitar duplicação de filtro
        // O Global Scope aplica where('empresa_id', ...) automaticamente, mas vamos
        // garantir que usamos o empresa_id dos filtros explicitamente
        $query = $modelClass::withoutGlobalScope('empresa')->query();
        
        // Obter nome da tabela do modelo
        $model = new $modelClass();
        $tableName = $model->getTable();
        
        // Filtrar por empresa_id (obrigatório) - aplicação explícita
        $query->where("{$tableName}.empresa_id", $filtros['empresa_id'])
              ->whereNotNull("{$tableName}.empresa_id");
        
        Log::debug(static::class . '->buscarComFiltros() - Query construída', [
            'empresa_id_filtro' => $filtros['empresa_id'],
            'table' => $tableName,
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        return $query;
    }

    /**
     * Valida que todos os registros retornados pertencem à empresa correta
     * 
     * @param LengthAwarePaginator $paginator
     * @param int $empresaIdEsperado
     * @return void
     */
    protected function validarEmpresaIds(LengthAwarePaginator $paginator, int $empresaIdEsperado): void
    {
        // Log detalhado dos IDs retornados para debug
        $idsRetornados = $paginator->getCollection()->pluck('id')->toArray();
        $empresasIdsRetornados = $paginator->getCollection()->pluck('empresa_id')->toArray();
        
        Log::debug(static::class . '->buscarComFiltros() resultado', [
            'empresa_id_filtro' => $empresaIdEsperado,
            'total' => $paginator->total(),
            'count' => $paginator->count(),
            'ids_retornados' => $idsRetornados,
            'empresas_ids_retornados' => $empresasIdsRetornados,
            'empresas_unicas' => array_unique($empresasIdsRetornados),
        ]);
        
        // VALIDAÇÃO CRÍTICA: Verificar se todos os registros pertencem à empresa correta
        $empresasInvalidas = array_filter($empresasIdsRetornados, function($empresaId) use ($empresaIdEsperado) {
            return $empresaId != $empresaIdEsperado;
        });
        
        if (!empty($empresasInvalidas)) {
            Log::error(static::class . '->buscarComFiltros() - DADOS DE OUTRA EMPRESA ENCONTRADOS!', [
                'empresa_id_filtro' => $empresaIdEsperado,
                'empresas_invalidas' => array_values($empresasInvalidas),
                'ids_invalidos' => array_keys($empresasInvalidas),
            ]);
            
            // Remover registros inválidos da coleção
            $paginator->getCollection()->transform(function ($item) use ($empresaIdEsperado) {
                if (is_object($item) && isset($item->empresa_id) && $item->empresa_id != $empresaIdEsperado) {
                    return null;
                }
                if (is_array($item) && isset($item['empresa_id']) && $item['empresa_id'] != $empresaIdEsperado) {
                    return null;
                }
                return $item;
            });
            
            $paginator->setCollection($paginator->getCollection()->filter());
        }
    }

    /**
     * Valida que um modelo pertence à empresa correta
     * 
     * @param Model|null $model
     * @param int $empresaIdEsperado
     * @param int|null $domainEmpresaId
     * @return Model|null Retorna null se não pertence à empresa
     */
    protected function validarModeloEmpresa(?Model $model, int $empresaIdEsperado, ?int $domainEmpresaId = null): ?Model
    {
        if (!$model) {
            return null;
        }

        // VALIDAÇÃO CRÍTICA: Garantir que o modelo pertence à empresa correta
        if ($model->empresa_id != $empresaIdEsperado) {
            Log::error(static::class . ' - Tentativa de acessar registro de outra empresa - BLOQUEADO', [
                'model_id' => $model->id,
                'model_empresa_id' => $model->empresa_id,
                'empresa_id_esperado' => $empresaIdEsperado,
                'domain_empresa_id' => $domainEmpresaId,
            ]);
            return null; // Não retornar registro de outra empresa
        }

        // Validação adicional: verificar consistência com domain (se fornecido)
        if ($domainEmpresaId !== null && $domainEmpresaId != $empresaIdEsperado) {
            Log::error(static::class . ' - Inconsistência: empresa_id do domain não corresponde', [
                'model_id' => $model->id,
                'empresa_id_domain' => $domainEmpresaId,
                'empresa_id_model' => $model->empresa_id,
                'empresa_id_esperado' => $empresaIdEsperado,
            ]);
            return null;
        }

        return $model;
    }
}

