<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Plano\Entities\Plano;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Modules\Assinatura\Models\Plano as PlanoModel;
use Illuminate\Support\Collection;

/**
 * Implementação do Repository de Plano usando Eloquent
 * Esta é a única camada que conhece Eloquent/banco de dados
 */
class PlanoRepository implements PlanoRepositoryInterface
{
    /**
     * Converter modelo Eloquent para entidade do domínio
     */
    private function toDomain(PlanoModel $model): Plano
    {
        return new Plano(
            id: $model->id,
            nome: $model->nome,
            descricao: $model->descricao,
            precoMensal: $model->preco_mensal ? (float) $model->preco_mensal : null,
            precoAnual: $model->preco_anual ? (float) $model->preco_anual : null,
            limiteProcessos: $model->limite_processos,
            limiteUsuarios: $model->limite_usuarios,
            limiteArmazenamentoMb: $model->limite_armazenamento_mb,
            recursosDisponiveis: $model->recursos_disponiveis,
            ativo: $model->ativo ?? true,
            ordem: $model->ordem,
        );
    }

    /**
     * Buscar plano por ID
     */
    public function buscarPorId(int $id): ?Plano
    {
        $model = PlanoModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    /**
     * Listar todos os planos
     * 
     * @param array $filtros Filtros opcionais
     * @return Collection<Plano>
     */
    public function listar(array $filtros = []): Collection
    {
        $query = PlanoModel::query();

        // Filtrar por ativo (padrão: apenas ativos)
        $ativo = $filtros['ativo'] ?? true;
        if ($ativo !== null) {
            $query->where('ativo', $ativo);
        }

        // Ordenar por ordem e depois por preço mensal
        $query->orderBy('ordem', 'asc')
              ->orderBy('preco_mensal', 'asc');

        $models = $query->get();

        return $models->map(fn($model) => $this->toDomain($model));
    }

    /**
     * Buscar modelo Eloquent por ID
     */
    public function buscarModeloPorId(int $id): ?PlanoModel
    {
        return PlanoModel::find($id);
    }

    /**
     * Salvar plano (criar ou atualizar)
     */
    public function salvar(Plano $plano): Plano
    {
        if ($plano->id) {
            // Atualizar
            $model = PlanoModel::findOrFail($plano->id);
            
            \Log::info('PlanoRepository::salvar - Atualizando plano', [
                'plano_id' => $plano->id,
                'dados' => [
                    'nome' => $plano->nome,
                    'preco_mensal' => $plano->precoMensal,
                    'preco_anual' => $plano->precoAnual,
                    'limite_processos' => $plano->limiteProcessos,
                    'limite_usuarios' => $plano->limiteUsuarios,
                    'limite_armazenamento_mb' => $plano->limiteArmazenamentoMb,
                    'ativo' => $plano->ativo,
                    'ordem' => $plano->ordem,
                ],
            ]);
            
            $model->fill([
                'nome' => $plano->nome,
                'descricao' => $plano->descricao,
                'preco_mensal' => $plano->precoMensal,
                'preco_anual' => $plano->precoAnual,
                'limite_processos' => $plano->limiteProcessos,
                'limite_usuarios' => $plano->limiteUsuarios,
                'limite_armazenamento_mb' => $plano->limiteArmazenamentoMb,
                'recursos_disponiveis' => $plano->recursosDisponiveis,
                'ativo' => $plano->ativo,
                'ordem' => $plano->ordem,
            ]);
            
            $saved = $model->save();
            
            \Log::info('PlanoRepository::salvar - Plano atualizado', [
                'plano_id' => $model->id,
                'saved' => $saved,
                'was_changed' => $model->wasChanged(),
            ]);
        } else {
            // Criar
            \Log::info('PlanoRepository::salvar - Criando novo plano', [
                'nome' => $plano->nome,
            ]);

            $model = PlanoModel::create([
                'nome' => $plano->nome,
                'descricao' => $plano->descricao,
                'preco_mensal' => $plano->precoMensal,
                'preco_anual' => $plano->precoAnual,
                'limite_processos' => $plano->limiteProcessos,
                'limite_usuarios' => $plano->limiteUsuarios,
                'limite_armazenamento_mb' => $plano->limiteArmazenamentoMb,
                'recursos_disponiveis' => $plano->recursosDisponiveis,
                'ativo' => $plano->ativo,
                'ordem' => $plano->ordem,
            ]);

            \Log::info('PlanoRepository::salvar - Plano criado', [
                'plano_id' => $model->id,
                'nome' => $model->nome,
            ]);
        }

        $planoDomain = $this->toDomain($model);

        \Log::info('PlanoRepository::salvar - Retornando plano domain', [
            'plano_id' => $planoDomain->id,
        ]);

        return $planoDomain;
    }

    /**
     * Deletar plano
     */
    public function deletar(int $id): void
    {
        PlanoModel::destroy($id);
    }
}

