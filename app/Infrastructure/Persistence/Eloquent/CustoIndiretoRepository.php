<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\CustoIndireto\Entities\CustoIndireto;
use App\Domain\CustoIndireto\Repositories\CustoIndiretoRepositoryInterface;
use App\Modules\Custo\Models\CustoIndireto as CustoIndiretoModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;
use App\Infrastructure\Persistence\Eloquent\Traits\IsolamentoEmpresaTrait;

class CustoIndiretoRepository implements CustoIndiretoRepositoryInterface
{
    use IsolamentoEmpresaTrait;
    private function toDomain(CustoIndiretoModel $model): CustoIndireto
    {
        return new CustoIndireto(
            id: $model->id,
            empresaId: $model->empresa_id,
            descricao: $model->descricao,
            data: $model->data ? Carbon::parse($model->data) : null,
            valor: (float) $model->valor,
            categoria: $model->categoria,
            observacoes: $model->observacoes,
        );
    }

    private function toArray(CustoIndireto $custo): array
    {
        return [
            'empresa_id' => $custo->empresaId,
            'descricao' => $custo->descricao,
            'data' => $custo->data?->toDateString(),
            'valor' => $custo->valor,
            'categoria' => $custo->categoria,
            'observacoes' => $custo->observacoes,
        ];
    }

    public function criar(CustoIndireto $custo): CustoIndireto
    {
        $model = CustoIndiretoModel::create($this->toArray($custo));
        return $this->toDomain($model->fresh());
    }

    public function buscarPorId(int $id): ?CustoIndireto
    {
        $model = CustoIndiretoModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        // Aplicar filtro de empresa_id com isolamento
        $query = $this->aplicarFiltroEmpresa(CustoIndiretoModel::class, $filtros);

        if (isset($filtros['categoria'])) {
            $query->where('categoria', $filtros['categoria']);
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy('criado_em', 'desc')->paginate($perPage);

        // Validar que todos os registros pertencem à empresa correta
        $this->validarEmpresaIds($paginator, $filtros['empresa_id']);

        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(CustoIndireto $custo): CustoIndireto
    {
        $model = CustoIndiretoModel::findOrFail($custo->id);
        $model->update($this->toArray($custo));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        CustoIndiretoModel::findOrFail($id)->delete();
    }
}

