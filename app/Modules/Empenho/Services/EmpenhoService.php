<?php

namespace App\Modules\Empenho\Services;

use App\Modules\Processo\Models\Processo;
use App\Modules\Empenho\Models\Empenho;
use App\Domain\Empenho\Repositories\EmpenhoRepositoryInterface;

/**
 * Service para Empenho
 * 
 * ⚠️ TEMPORÁRIO: Este service é um stub para manter compatibilidade com o controller.
 * Idealmente, o controller deveria ser refatorado para usar Use Cases diretamente.
 */
class EmpenhoService
{
    public function __construct(
        private EmpenhoRepositoryInterface $repository,
    ) {}

    public function update(Processo $processo, Empenho $empenho, array $data, int $empresaId): Empenho
    {
        // TODO: Refatorar para usar Use Case
        throw new \RuntimeException('Método update precisa ser implementado usando Use Case');
    }

    public function delete(Processo $processo, Empenho $empenho, int $empresaId): void
    {
        $domainEntity = $this->repository->buscarPorId($empenho->id);
        
        if (!$domainEntity || $domainEntity->empresaId !== $empresaId) {
            throw new \RuntimeException('Empenho não encontrado.');
        }

        $this->repository->deletar($empenho->id);
    }
}


