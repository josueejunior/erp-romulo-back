<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Support\Str;

class AuditObserver
{
    /**
     * Handle the model "created" event.
     */
    public function created($model): void
    {
        if (!$this->shouldAudit($model)) {
            return;
        }

        AuditLog::log(
            'created',
            $model,
            null,
            $model->getAttributes(),
            "Criado {$this->getModelName($model)}"
        );
    }

    /**
     * Handle the model "updated" event.
     */
    public function updated($model): void
    {
        if (!$this->shouldAudit($model)) {
            return;
        }

        // Ignorar atualizações de timestamps apenas
        $dirty = $model->getDirty();
        if (count($dirty) === 0 || (count($dirty) === 2 && isset($dirty['updated_at']))) {
            return;
        }

        AuditLog::log(
            'updated',
            $model,
            $model->getOriginal(),
            $model->getAttributes(),
            "Atualizado {$this->getModelName($model)}"
        );
    }

    /**
     * Handle the model "deleted" event.
     */
    public function deleted($model): void
    {
        if (!$this->shouldAudit($model)) {
            return;
        }

        AuditLog::log(
            'deleted',
            $model,
            $model->getAttributes(),
            null,
            "Excluído {$this->getModelName($model)}"
        );
    }

    /**
     * Verifica se o modelo deve ser auditado
     */
    protected function shouldAudit($model): bool
    {
        // Só auditar se houver usuário autenticado
        if (!auth()->check()) {
            return false;
        }

        // Lista de modelos que devem ser auditados
        $auditableModels = [
            \App\Models\Processo::class,
            \App\Models\Contrato::class,
            \App\Models\Orcamento::class,
            \App\Models\NotaFiscal::class,
            \App\Models\Empenho::class,
            \App\Models\AutorizacaoFornecimento::class,
        ];

        return in_array(get_class($model), $auditableModels);
    }

    /**
     * Retorna nome amigável do modelo
     */
    protected function getModelName($model): string
    {
        $className = class_basename($model);
        return str_replace('_', ' ', Str::snake($className));
    }
}
