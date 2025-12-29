<?php

namespace App\Infrastructure\Persistence\Eloquent\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Trait para padronizar a busca de modelos Eloquent nos repositories
 * 
 * Este trait garante que todos os repositories tenham um método consistente
 * para buscar modelos Eloquent quando necessário (ex: para JsonResources),
 * mantendo o Global Scope de Empresa ativo para segurança.
 * 
 * IMPORTANTE: Este método deve ser usado APENAS quando necessário converter
 * uma entidade de domínio para um modelo Eloquent (ex: para Resources do Laravel).
 * Para lógica de negócio, sempre use buscarPorId() que retorna uma entidade de domínio.
 */
trait HasModelRetrieval
{
    /**
     * Busca um modelo Eloquent por ID, mantendo o Global Scope de Empresa ativo
     * 
     * Este método é útil quando você precisa do modelo Eloquent para:
     * - JsonResources do Laravel
     * - Eager loading de relacionamentos
     * - Outras operações que requerem o modelo Eloquent
     * 
     * @param int $id ID do modelo
     * @param array $with Relacionamentos para eager load (opcional)
     * @param bool $failIfNotFound Se true, lança exceção se não encontrado
     * @return Model|null Modelo Eloquent ou null se não encontrado
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Se não encontrado e $failIfNotFound = true
     */
    protected function buscarModeloPorIdInternal(int $id, array $with = [], bool $failIfNotFound = false): ?Model
    {
        $modelClass = $this->getModelClass();
        
        if (!$modelClass) {
            Log::error(static::class . '::buscarModeloPorIdInternal() - Model class não definida');
            throw new \RuntimeException('Model class não definida no repository');
        }

        $query = $modelClass::query();

        // Eager load relacionamentos se especificado
        if (!empty($with)) {
            $query->with($with);
        }

        // O Global Scope de Empresa já está ativo por padrão
        // Não removemos o scope aqui para manter a segurança
        if ($failIfNotFound) {
            return $query->findOrFail($id);
        }

        return $query->find($id);
    }

    /**
     * Retorna a classe do modelo Eloquent
     * 
     * Cada repository deve implementar este método para retornar
     * a classe do modelo que ele gerencia.
     * 
     * @return string|null Nome da classe do modelo
     */
    abstract protected function getModelClass(): ?string;
}

