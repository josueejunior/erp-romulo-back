<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Plano\Entities\Plano;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Modules\Assinatura\Models\Plano as PlanoModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
        Log::info('🔍 PlanoRepository::listar - Iniciando', [
            'filtros' => $filtros,
            'database' => config('database.default'),
            'connection' => config('database.connections.' . config('database.default') . '.database'),
        ]);

        $query = PlanoModel::query();

        // Filtrar por ativo (padrão: apenas ativos)
        $ativo = $filtros['ativo'] ?? true;
        if ($ativo !== null) {
            Log::info('🔍 PlanoRepository::listar - Aplicando filtro ativo', [
                'ativo' => $ativo,
            ]);
            $query->where('ativo', $ativo);
        }

        // 🔥 CORREÇÃO: Excluir planos gratuitos por padrão (exceto se explicitamente solicitado)
        // Planos gratuitos não devem aparecer na listagem de assinaturas para renovar/trocar
        $incluirGratuitos = $filtros['incluir_gratuitos'] ?? false;
        if (!$incluirGratuitos) {
            Log::info('🔍 PlanoRepository::listar - Excluindo planos gratuitos', [
                'incluir_gratuitos' => $incluirGratuitos,
            ]);
            // Excluir planos onde preco_mensal é 0 ou null
            $query->where(function($q) {
                $q->where('preco_mensal', '>', 0)
                  ->orWhere(function($q2) {
                      // Se preco_mensal for null, verificar se preco_anual > 0
                      $q2->whereNull('preco_mensal')
                         ->where('preco_anual', '>', 0);
                  });
            });
        }

        // Ordenar por ordem e depois por preço mensal
        $query->orderBy('ordem', 'asc')
              ->orderBy('preco_mensal', 'asc');

        Log::info('🔍 PlanoRepository::listar - Executando query', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        $models = $query->get();

        Log::info('🔍 PlanoRepository::listar - Query executada', [
            'models_count' => $models->count(),
            'models_ids' => $models->pluck('id')->toArray(),
            'models_data' => $models->map(fn($m) => [
                'id' => $m->id,
                'nome' => $m->nome,
                'ativo' => $m->ativo,
                'preco_mensal' => $m->preco_mensal,
            ])->toArray(),
        ]);

        $planosDomain = $models->map(fn($model) => $this->toDomain($model));

        Log::info('✅ PlanoRepository::listar - Retornando planos domain', [
            'count' => $planosDomain->count(),
            'ids' => $planosDomain->pluck('id')->toArray(),
        ]);

        return $planosDomain;
    }

    /**
     * Buscar modelo Eloquent por ID
     */
    public function buscarModeloPorId(int $id): ?PlanoModel
    {
        Log::debug('🔍 PlanoRepository::buscarModeloPorId', [
            'id' => $id,
        ]);

        $modelo = PlanoModel::find($id);

        Log::debug('🔍 PlanoRepository::buscarModeloPorId - Resultado', [
            'id' => $id,
            'encontrado' => $modelo !== null,
            'nome' => $modelo?->nome,
        ]);

        return $modelo;
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

