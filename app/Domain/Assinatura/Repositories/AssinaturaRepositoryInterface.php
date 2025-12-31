<?php

namespace App\Domain\Assinatura\Repositories;

use App\Domain\Assinatura\Entities\Assinatura;
use Illuminate\Support\Collection;

/**
 * Interface do Repository de Assinatura
 */
interface AssinaturaRepositoryInterface
{
    /**
     * Buscar assinatura por ID
     */
    public function buscarPorId(int $id): ?Assinatura;

    /**
     * Buscar assinatura atual do tenant
     * 
     * @param int $tenantId ID do tenant
     * @return Assinatura|null
     */
    public function buscarAssinaturaAtual(int $tenantId): ?Assinatura;

    /**
     * Listar assinaturas do tenant
     * 
     * @param int $tenantId ID do tenant
     * @param array $filtros Filtros opcionais
     * @return Collection<Assinatura>
     */
    public function listarPorTenant(int $tenantId, array $filtros = []): Collection;

    /**
     * Buscar modelo Eloquent por ID (para casos especiais onde precisa do modelo, não da entidade)
     * Use apenas quando realmente necessário (ex: relacionamentos)
     * 
     * @return \App\Modules\Assinatura\Models\Assinatura|null
     */
    public function buscarModeloPorId(int $id): ?\App\Modules\Assinatura\Models\Assinatura;

    /**
     * Buscar modelo Eloquent por transacao_id (para webhooks)
     * 
     * @param string $transacaoId ID da transação no gateway
     * @return \App\Modules\Assinatura\Models\Assinatura|null
     */
    public function buscarModeloPorTransacaoId(string $transacaoId): ?\App\Modules\Assinatura\Models\Assinatura;

    /**
     * Salvar assinatura (criar ou atualizar)
     * 
     * @param Assinatura $assinatura Entidade do domínio
     * @return Assinatura Entidade salva com ID
     */
    public function salvar(Assinatura $assinatura): Assinatura;
}

