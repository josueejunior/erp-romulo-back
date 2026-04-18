<?php

namespace App\Domain\Empresa\Repositories;

use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Domain\Empresa\Entities\Empresa;

/**
 * Interface do Repository de Empresa
 */
interface EmpresaRepositoryInterface
{
    /**
     * Criar empresa dentro de um tenant
     */
    public function criarNoTenant(int $tenantId, CriarTenantDTO $dto): Empresa;

    /**
     * Buscar empresa por ID
     */
    public function buscarPorId(int $id): ?Empresa;

    /**
     * Listar todas as empresas do tenant atual
     */
    public function listar(): array;

    /**
     * Buscar modelo Eloquent por ID (para casos especiais onde precisa do modelo, não da entidade)
     * Use apenas quando realmente necessário (ex: BaseApiController que precisa de relacionamentos)
     * 
     * @return \App\Models\Empresa|null
     */
    public function buscarModeloPorId(int $id): ?\App\Models\Empresa;

    /**
     * Atualizar dados do afiliado na empresa
     */
    public function atualizarAfiliado(
        int $empresaId,
        int $afiliadoId,
        string $codigo,
        float $descontoAplicado
    ): void;
}




