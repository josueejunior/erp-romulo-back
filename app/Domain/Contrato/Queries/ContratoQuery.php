<?php

namespace App\Domain\Contrato\Queries;

use App\Application\Contrato\DTOs\ContratoFiltroDTO;
use App\Modules\Contrato\Models\Contrato as ContratoModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query Object para Contratos
 * 
 * Responsabilidades:
 * - Aplicar filtros complexos de forma declarativa
 * - Usar when() para reduzir ifs
 * - Separar cada filtro em método privado (SRP)
 * - NUNCA validar (apenas consultar)
 * 
 * Nível: Enterprise DDD
 */
class ContratoQuery
{
    /**
     * Aplica filtros complexos à query de contratos
     * 
     * ✅ Usa when() para código declarativo
     * ✅ Cada filtro em método separado (SRP)
     * ✅ Não valida (validação deve ser feita antes)
     */
    public static function aplicarFiltros(
        Builder $query,
        ContratoFiltroDTO $filtros,
        int $empresaId
    ): Builder {
        self::filtroBusca($query, $filtros, $empresaId);
        self::filtroOrgao($query, $filtros);
        self::filtroSRP($query, $filtros);
        self::filtroSituacao($query, $filtros);
        self::filtroVigencia($query, $filtros);
        self::filtroVencerEm($query, $filtros);
        self::filtroAlertas($query, $filtros);

        return $query;
    }

    /**
     * Filtro: busca (número do contrato, processo, órgão)
     * 
     * Busca em:
     * - Número do contrato
     * - Número da modalidade do processo
     * - Número do processo administrativo
     * - Razão social do órgão
     * - UASG do órgão
     */
    private static function filtroBusca(Builder $query, ContratoFiltroDTO $filtros, int $empresaId): void
    {
        $query->when($filtros->busca, function ($q) use ($filtros, $empresaId) {
            $q->where(function($subQuery) use ($filtros, $empresaId) {
                $subQuery->where('numero', 'like', "%{$filtros->busca}%")
                    ->orWhereHas('processo', function($p) use ($filtros) {
                        $p->where('numero_modalidade', 'like', "%{$filtros->busca}%")
                            ->orWhere('numero_processo_administrativo', 'like', "%{$filtros->busca}%");
                    })
                    ->orWhereHas('processo.orgao', function($o) use ($filtros, $empresaId) {
                        $o->where('empresa_id', $empresaId)
                            ->where(function($q) use ($filtros) {
                                $q->where('razao_social', 'like', "%{$filtros->busca}%")
                                    ->orWhere('uasg', 'like', "%{$filtros->busca}%");
                            });
                    });
            });
        });
    }

    /**
     * Filtro: órgão
     * 
     * ⚠️ IMPORTANTE: Não valida aqui!
     * A validação de que o órgão pertence à empresa deve ser feita ANTES,
     * no Service ou Controller.
     */
    private static function filtroOrgao(Builder $query, ContratoFiltroDTO $filtros): void
    {
        $query->when($filtros->orgaoId, function ($q) use ($filtros) {
            $q->whereHas('processo', function($subQuery) use ($filtros) {
                $subQuery->where('orgao_id', $filtros->orgaoId);
            });
        });
    }

    /**
     * Filtro: tipo (SRP ou não)
     */
    private static function filtroSRP(Builder $query, ContratoFiltroDTO $filtros): void
    {
        $query->when($filtros->srp !== null, function ($q) use ($filtros) {
            $q->whereHas('processo', function($subQuery) use ($filtros) {
                $subQuery->where('srp', $filtros->srp);
            });
        });
    }

    /**
     * Filtro: situação (vigente, encerrado, cancelado)
     */
    private static function filtroSituacao(Builder $query, ContratoFiltroDTO $filtros): void
    {
        $query->when($filtros->situacao, function ($q) use ($filtros) {
            $q->where('situacao', $filtros->situacao);
        });
    }

    /**
     * Filtro: vigência (true/false)
     */
    private static function filtroVigencia(Builder $query, ContratoFiltroDTO $filtros): void
    {
        $query->when($filtros->vigente !== null, function ($q) use ($filtros) {
            $q->where('vigente', $filtros->vigente);
        });
    }

    /**
     * Filtro: vigência a vencer (30/60/90 dias)
     */
    private static function filtroVencerEm(Builder $query, ContratoFiltroDTO $filtros): void
    {
        $query->when($filtros->vencerEm, function ($q) use ($filtros) {
            $dataLimite = Carbon::now()->addDays($filtros->vencerEm);
            $q->where('data_fim', '<=', $dataLimite)
                ->where('data_fim', '>=', Carbon::now())
                ->where('vigente', true);
        });
    }

    /**
     * Filtro: somente com alerta
     * 
     * ✅ Usa scope do Model (reutilizável)
     * ✅ Regra de negócio isolada
     */
    private static function filtroAlertas(Builder $query, ContratoFiltroDTO $filtros): void
    {
        $query->when($filtros->somenteAlerta, function ($q) {
            $q->comAlerta();
        });
    }

    /**
     * Cria query base para contratos com relacionamentos
     * 
     * ✅ Centraliza relacionamentos necessários
     * ✅ Garante empresa_id sempre presente
     */
    public static function criarQueryBase(int $empresaId): Builder
    {
        return ContratoModel::where('empresa_id', $empresaId)
            ->whereNotNull('empresa_id')
            ->with([
                'processo:id,numero_modalidade,numero_processo_administrativo,orgao_id,setor_id,srp',
                'processo.orgao:id,uasg,razao_social',
                'processo.setor:id,nome',
                'empenhos:id,contrato_id,numero,valor',
                'autorizacoesFornecimento:id,contrato_id,numero'
            ]);
    }
}
