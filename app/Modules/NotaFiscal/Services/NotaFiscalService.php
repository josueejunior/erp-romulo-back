<?php

namespace App\Modules\NotaFiscal\Services;

use App\Modules\Processo\Models\Processo;
use App\Modules\NotaFiscal\Models\NotaFiscal;
use App\Domain\NotaFiscal\Repositories\NotaFiscalRepositoryInterface;
use Illuminate\Http\Request;

/**
 * Service para Nota Fiscal
 * 
 * ⚠️ TEMPORÁRIO: Este service é um stub para manter compatibilidade com o controller.
 * Idealmente, o controller deveria ser refatorado para usar Use Cases diretamente.
 */
class NotaFiscalService
{
    public function __construct(
        private NotaFiscalRepositoryInterface $repository,
    ) {}

    public function update(Processo $processo, NotaFiscal $notaFiscal, array $data, Request $request, int $empresaId): NotaFiscal
    {
        // TODO: Refatorar para usar Use Case
        throw new \RuntimeException('Método update precisa ser implementado usando Use Case');
    }

    public function delete(Processo $processo, NotaFiscal $notaFiscal, int $empresaId): void
    {
        $domainEntity = $this->repository->buscarPorId($notaFiscal->id);
        
        if (!$domainEntity || $domainEntity->empresaId !== $empresaId) {
            throw new \RuntimeException('Nota fiscal não encontrada.');
        }

        $this->repository->deletar($notaFiscal->id);
    }
}

