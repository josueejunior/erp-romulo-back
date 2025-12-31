<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\DocumentoHabilitacao\Entities\DocumentoHabilitacao;
use App\Domain\DocumentoHabilitacao\Repositories\DocumentoHabilitacaoRepositoryInterface;
use App\Modules\Documento\Models\DocumentoHabilitacao as DocumentoHabilitacaoModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use App\Infrastructure\Persistence\Eloquent\Traits\IsolamentoEmpresaTrait;

class DocumentoHabilitacaoRepository implements DocumentoHabilitacaoRepositoryInterface
{
    use IsolamentoEmpresaTrait;
    private function toDomain(DocumentoHabilitacaoModel $model): DocumentoHabilitacao
    {
        return new DocumentoHabilitacao(
            id: $model->id,
            empresaId: $model->empresa_id,
            tipo: $model->tipo,
            numero: $model->numero,
            identificacao: $model->identificacao,
            dataEmissao: $model->data_emissao ? Carbon::parse($model->data_emissao) : null,
            dataValidade: $model->data_validade ? Carbon::parse($model->data_validade) : null,
            arquivo: $model->arquivo,
            ativo: $model->ativo ?? true,
            observacoes: $model->observacoes,
        );
    }

    private function toArray(DocumentoHabilitacao $documento): array
    {
        return [
            'empresa_id' => $documento->empresaId,
            'tipo' => $documento->tipo,
            'numero' => $documento->numero,
            'identificacao' => $documento->identificacao,
            'data_emissao' => $documento->dataEmissao?->toDateString(),
            'data_validade' => $documento->dataValidade?->toDateString(),
            'arquivo' => $documento->arquivo,
            'ativo' => $documento->ativo,
            'observacoes' => $documento->observacoes,
        ];
    }

    public function criar(DocumentoHabilitacao $documento): DocumentoHabilitacao
    {
        $model = DocumentoHabilitacaoModel::create($this->toArray($documento));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?DocumentoHabilitacao
    {
        $model = DocumentoHabilitacaoModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        // Aplicar filtro de empresa_id com isolamento
        $query = $this->aplicarFiltroEmpresa(DocumentoHabilitacaoModel::class, $filtros);

        // Filtro por tipo
        if (isset($filtros['tipo'])) {
            $query->where('tipo', 'ilike', '%' . $filtros['tipo'] . '%');
        }

        // Filtro por busca livre (tipo, numero, identificacao)
        if (isset($filtros['search']) && $filtros['search']) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('tipo', 'ilike', "%{$search}%")
                  ->orWhere('numero', 'ilike', "%{$search}%")
                  ->orWhere('identificacao', 'ilike', "%{$search}%");
            });
        }

        // Filtro por status ativo
        if (isset($filtros['ativo'])) {
            $query->where('ativo', $filtros['ativo']);
        }

        // Filtro de documentos vencendo (próximos 30 dias)
        if (isset($filtros['vencendo']) && $filtros['vencendo']) {
            $hoje = Carbon::now()->startOfDay();
            $em30Dias = Carbon::now()->addDays(30)->endOfDay();
            $query->whereNotNull('data_validade')
                  ->where('data_validade', '>=', $hoje)
                  ->where('data_validade', '<=', $em30Dias);
        }

        // Filtro de documentos vencidos
        if (isset($filtros['vencidos']) && $filtros['vencidos']) {
            $hoje = Carbon::now()->startOfDay();
            $query->whereNotNull('data_validade')
                  ->where('data_validade', '<', $hoje);
        }

        // Filtros de data de validade customizados
        if (isset($filtros['data_validade_inicio'])) {
            $query->where('data_validade', '>=', $filtros['data_validade_inicio']);
        }

        if (isset($filtros['data_validade_fim'])) {
            $query->where('data_validade', '<=', $filtros['data_validade_fim']);
        }

        // Filtrar apenas documentos com data_validade não nula
        if (isset($filtros['data_validade_inicio']) || isset($filtros['data_validade_fim'])) {
            $query->whereNotNull('data_validade');
        }

        $perPage = $filtros['per_page'] ?? 15;
        
        // Ordenação
        if (isset($filtros['vencendo']) && $filtros['vencendo']) {
            // Documentos vencendo: ordenar por data de validade (mais próximo primeiro)
            $query->orderBy('data_validade', 'asc');
        } elseif (isset($filtros['vencidos']) && $filtros['vencidos']) {
            // Documentos vencidos: ordenar por data de validade (mais recente primeiro)
            $query->orderBy('data_validade', 'desc');
        } elseif (isset($filtros['data_validade_inicio']) || isset($filtros['data_validade_fim'])) {
            if (isset($filtros['data_validade_fim']) && !isset($filtros['data_validade_inicio'])) {
                // Documentos vencidos: ordenar desc
                $query->orderBy('data_validade', 'desc');
            } else {
                // Documentos vencendo: ordenar asc
                $query->orderBy('data_validade', 'asc');
            }
        } else {
            $query->orderBy('criado_em', 'desc');
        }
        
        $paginator = $query->paginate($perPage);

        // Validar que todos os registros pertencem à empresa correta
        $this->validarEmpresaIds($paginator, $filtros['empresa_id'] ?? null);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    /**
     * Buscar documentos vencendo (próximos 30 dias)
     */
    public function buscarVencendo(int $empresaId, int $dias = 30): array
    {
        $hoje = Carbon::now()->startOfDay();
        $dataLimite = Carbon::now()->addDays($dias)->endOfDay();

        $models = DocumentoHabilitacaoModel::where('empresa_id', $empresaId)
            ->whereNotNull('data_validade')
            ->where('data_validade', '>=', $hoje)
            ->where('data_validade', '<=', $dataLimite)
            ->where('ativo', true)
            ->orderBy('data_validade', 'asc')
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->toArray();
    }

    /**
     * Buscar documentos vencidos
     */
    public function buscarVencidos(int $empresaId): array
    {
        $hoje = Carbon::now()->startOfDay();

        $models = DocumentoHabilitacaoModel::where('empresa_id', $empresaId)
            ->whereNotNull('data_validade')
            ->where('data_validade', '<', $hoje)
            ->where('ativo', true)
            ->orderBy('data_validade', 'desc')
            ->get();

        return $models->map(fn($model) => $this->toDomain($model))->toArray();
    }

    public function atualizar(DocumentoHabilitacao $documento): DocumentoHabilitacao
    {
        $model = DocumentoHabilitacaoModel::findOrFail($documento->id);
        $model->update($this->toArray($documento));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        DocumentoHabilitacaoModel::findOrFail($id)->delete();
    }
}


