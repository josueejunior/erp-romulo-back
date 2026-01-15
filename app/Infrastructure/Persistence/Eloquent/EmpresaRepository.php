<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Domain\Empresa\Entities\Empresa;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use App\Models\Empresa as EmpresaModel;

/**
 * Implementação do Repository de Empresa usando Eloquent
 */
class EmpresaRepository implements EmpresaRepositoryInterface
{
    /**
     * Converter modelo Eloquent para entidade do domínio
     */
    private function toDomain(EmpresaModel $model): Empresa
    {
        return new Empresa(
            id: $model->id,
            tenantId: $model->tenant_id ?? 0, // Empresas estão no banco do tenant
            razaoSocial: $model->razao_social,
            cnpj: $model->cnpj,
            email: $model->email,
            status: $model->status ?? 'ativa',
            endereco: $model->endereco ?? $model->logradouro,
            cidade: $model->cidade,
            estado: $model->estado,
            cep: $model->cep,
            telefones: $model->telefones,
            emails: $model->emails_adicionais,
            bancoNome: $model->banco_nome,
            bancoAgencia: $model->banco_agencia,
            bancoConta: $model->banco_conta,
            bancoTipo: $model->banco_tipo,
            bancoPix: null, // Coluna não existe na tabela empresas
            representanteLegal: $model->representante_legal,
            logo: $model->logo,
        );
    }

    public function criarNoTenant(int $tenantId, CriarTenantDTO $dto): Empresa
    {
        // Verificar se tenancy está inicializado
        if (!tenancy()->initialized) {
            \Log::warning('Tenancy não inicializado ao criar empresa no tenant', [
                'tenant_id' => $tenantId,
            ]);
            throw new \RuntimeException('Tenancy não está inicializado. Não é possível criar empresa.');
        }
        
        \Log::debug('Criando empresa no tenant', [
            'tenant_id' => $tenantId,
            'razao_social' => $dto->razaoSocial,
            'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
        ]);
        
        $statusFinal = $dto->status ?? 'ativa';
        
        \Log::info('EmpresaRepository::criarNoTenant - Criando empresa', [
            'tenant_id' => $tenantId,
            'razao_social' => $dto->razaoSocial,
            'cnpj' => $dto->cnpj,
            'status_recebido' => $dto->status,
            'status_final' => $statusFinal,
            'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
        ]);
        
        $model = EmpresaModel::create([
            'razao_social' => $dto->razaoSocial,
            'cnpj' => $dto->cnpj,
            'email' => $dto->email,
            'logradouro' => $dto->endereco, // A migration cria 'logradouro', não 'endereco'
            'cidade' => $dto->cidade,
            'estado' => $dto->estado,
            'cep' => $dto->cep,
            'telefones' => $dto->telefones,
            'emails_adicionais' => $dto->emailsAdicionais,
            'banco_nome' => $dto->banco,
            'banco_agencia' => $dto->agencia,
            'banco_conta' => $dto->conta,
            'banco_tipo' => $dto->tipoConta,
            // 'banco_pix' => $dto->pix, // Coluna não existe na tabela empresas
            'representante_legal' => $dto->representanteLegalNome,
            'logo' => $dto->logo,
            'status' => $statusFinal,
        ]);
        
        // 🔥 DEBUG: Log detalhado após criação
        \Log::info('EmpresaRepository::criarNoTenant - Empresa criada no banco', [
            'tenant_id' => $tenantId,
            'empresa_id' => $model->id,
            'razao_social' => $model->razao_social,
            'cnpj' => $model->cnpj,
            'status' => $model->status,
            'status_verificado' => $model->status === 'ativa' ? '✅ ATIVA' : '❌ INATIVA',
            'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
            'criado_em' => $model->criado_em?->toDateTimeString(),
        ]);
        
        // 🔥 PERFORMANCE: Criar mapeamento direto empresa → tenant
        try {
            \App\Models\TenantEmpresa::createOrUpdateMapping($tenantId, $model->id);
            \Log::info('EmpresaRepository::criarNoTenant() - Mapeamento criado', [
                'tenant_id' => $tenantId,
                'empresa_id' => $model->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('EmpresaRepository::criarNoTenant() - Erro ao criar mapeamento', [
                'tenant_id' => $tenantId,
                'empresa_id' => $model->id,
                'error' => $e->getMessage(),
            ]);
            // Não lançar exceção - mapeamento é otimização, não crítico
        }

        return $this->toDomain($model);
    }

    public function buscarPorId(int $id): ?Empresa
    {
        $model = EmpresaModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function listar(): array
    {
        // 🔥 DEBUG: Log detalhado das empresas encontradas no banco
        $empresasModel = EmpresaModel::all();
        
        $empresasDetalhes = $empresasModel->map(function ($model) {
            return [
                'id' => $model->id,
                'razao_social' => $model->razao_social,
                'cnpj' => $model->cnpj,
                'status' => $model->status,
                'tenant_id' => $model->tenant_id ?? null,
                'criado_em' => $model->criado_em?->toDateTimeString(),
                'atualizado_em' => $model->atualizado_em?->toDateTimeString(),
                'deleted_at' => $model->deleted_at?->toDateTimeString(),
            ];
        })->toArray();
        
        \Log::info('EmpresaRepository::listar - Empresas encontradas no banco', [
            'total_empresas' => $empresasModel->count(),
            'tenant_id' => tenancy()->tenant?->id,
            'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
            'empresas_detalhes' => $empresasDetalhes,
        ]);
        
        $result = $empresasModel->map(function ($model) {
            return $this->toDomain($model);
        })->toArray();
        
        \Log::info('EmpresaRepository::listar - Empresas após conversão para domínio', [
            'total_empresas' => count($result),
            'empresas_ids' => array_column($result, 'id'),
            'empresas_razao_social' => array_column($result, 'razaoSocial'),
        ]);
        
        return $result;
    }

    /**
     * Buscar modelo Eloquent por ID (para casos especiais onde precisa do modelo, não da entidade)
     * Use apenas quando realmente necessário (ex: BaseApiController que precisa de relacionamentos)
     */
    public function buscarModeloPorId(int $id): ?EmpresaModel
    {
        return EmpresaModel::find($id);
    }

    /**
     * Atualizar dados do afiliado na empresa
     */
    public function atualizarAfiliado(
        int $empresaId,
        int $afiliadoId,
        string $codigo,
        float $descontoAplicado
    ): void {
        $model = EmpresaModel::find($empresaId);
        
        if (!$model) {
            throw new DomainException('Empresa não encontrada.');
        }

        $model->update([
            'afiliado_id' => $afiliadoId,
            'afiliado_codigo' => $codigo,
            'afiliado_desconto_aplicado' => $descontoAplicado,
            'afiliado_aplicado_em' => now(),
        ]);

        \Log::info('EmpresaRepository::atualizarAfiliado() - Afiliado registrado na empresa', [
            'empresa_id' => $empresaId,
            'afiliado_id' => $afiliadoId,
            'codigo' => $codigo,
            'desconto' => $descontoAplicado,
        ]);
    }
}




