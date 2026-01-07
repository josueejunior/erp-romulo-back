<?php

namespace App\Modules\AutorizacaoFornecimento\Services;

use App\Modules\Processo\Models\Processo;
use App\Modules\AutorizacaoFornecimento\Models\AutorizacaoFornecimento;
use App\Domain\AutorizacaoFornecimento\Repositories\AutorizacaoFornecimentoRepositoryInterface;

/**
 * Service para Autorização de Fornecimento
 * 
 * ⚠️ TEMPORÁRIO: Este service é um stub para manter compatibilidade com o controller.
 * Idealmente, o controller deveria ser refatorado para usar Use Cases diretamente.
 */
class AutorizacaoFornecimentoService
{
    public function __construct(
        private AutorizacaoFornecimentoRepositoryInterface $repository,
    ) {}

    public function listByProcesso(Processo $processo, int $empresaId): array
    {
        $autorizacoes = $this->repository->buscarComFiltros([
            'processo_id' => $processo->id,
            'empresa_id' => $empresaId,
        ]);

        return $autorizacoes->items();
    }

    public function store(Processo $processo, array $data, int $empresaId): AutorizacaoFornecimento
    {
        // TODO: Refatorar para usar CriarAutorizacaoFornecimentoUseCase
        throw new \RuntimeException('Método store precisa ser implementado usando Use Case');
    }

    public function find(Processo $processo, AutorizacaoFornecimento $autorizacao, int $empresaId): AutorizacaoFornecimento
    {
        $domainEntity = $this->repository->buscarPorId($autorizacao->id);
        
        if (!$domainEntity || $domainEntity->empresaId !== $empresaId) {
            throw new \RuntimeException('Autorização de fornecimento não encontrada.');
        }

        return $domainEntity;
    }

    public function update(Processo $processo, AutorizacaoFornecimento $autorizacao, array $data, int $empresaId): AutorizacaoFornecimento
    {
        // TODO: Refatorar para usar Use Case
        throw new \RuntimeException('Método update precisa ser implementado usando Use Case');
    }

    public function delete(Processo $processo, AutorizacaoFornecimento $autorizacao, int $empresaId): void
    {
        $domainEntity = $this->repository->buscarPorId($autorizacao->id);
        
        if (!$domainEntity || $domainEntity->empresaId !== $empresaId) {
            throw new \RuntimeException('Autorização de fornecimento não encontrada.');
        }

        $this->repository->deletar($autorizacao->id);
    }
}


