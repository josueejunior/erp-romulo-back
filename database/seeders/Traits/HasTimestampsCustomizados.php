<?php

namespace Database\Seeders\Traits;

/**
 * Trait para seeders que precisam usar timestamps customizados
 * Fornece métodos auxiliares para criar registros com timestamps em português
 */
trait HasTimestampsCustomizados
{
    /**
     * Adiciona timestamps customizados aos dados
     */
    protected function withTimestamps(array $data): array
    {
        $now = now();
        return array_merge($data, [
            'criado_em' => $now,
            'atualizado_em' => $now,
        ]);
    }

    /**
     * Cria um registro com timestamps customizados
     */
    protected function createWithTimestamps(string $model, array $data)
    {
        return $model::create($this->withTimestamps($data));
    }

    /**
     * Atualiza ou cria um registro com timestamps customizados
     */
    protected function updateOrCreateWithTimestamps(string $model, array $conditions, array $data)
    {
        $data = $this->withTimestamps($data);
        return $model::updateOrCreate($conditions, $data);
    }
}





