<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Tenant\Entities\Tenant;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Models\Tenant as TenantModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Implementação do Repository de Tenant usando Eloquent
 * Esta é a única camada que conhece Eloquent/banco de dados
 */
class TenantRepository implements TenantRepositoryInterface
{
    /**
     * Converter modelo Eloquent para entidade do domínio
     */
    private function toDomain(TenantModel $model): Tenant
    {
        return new Tenant(
            id: $model->id,
            razaoSocial: $model->razao_social,
            cnpj: $model->cnpj,
            email: $model->email,
            status: $model->status ?? 'ativa',
            endereco: $model->endereco,
            cidade: $model->cidade,
            estado: $model->estado,
            cep: $model->cep,
            telefones: $model->telefones,
            emailsAdicionais: $model->emails_adicionais,
            banco: $model->banco,
            agencia: $model->agencia,
            conta: $model->conta,
            tipoConta: $model->tipo_conta,
            pix: $model->pix,
            representanteLegalNome: $model->representante_legal_nome,
            representanteLegalCpf: $model->representante_legal_cpf,
            representanteLegalCargo: $model->representante_legal_cargo,
            logo: $model->logo,
        );
    }

    /**
     * Converter entidade do domínio para array do Eloquent
     */
    private function toArray(Tenant $tenant): array
    {
        return [
            'razao_social' => $tenant->razaoSocial,
            'cnpj' => $tenant->cnpj,
            'email' => $tenant->email,
            'status' => $tenant->status ?? 'ativa',
            'endereco' => $tenant->endereco,
            'cidade' => $tenant->cidade,
            'estado' => $tenant->estado,
            'cep' => $tenant->cep,
            'telefones' => $tenant->telefones,
            'emails_adicionais' => $tenant->emailsAdicionais,
            'banco' => $tenant->banco,
            'agencia' => $tenant->agencia,
            'conta' => $tenant->conta,
            'tipo_conta' => $tenant->tipoConta,
            'pix' => $tenant->pix,
            'representante_legal_nome' => $tenant->representanteLegalNome,
            'representante_legal_cpf' => $tenant->representanteLegalCpf,
            'representante_legal_cargo' => $tenant->representanteLegalCargo,
            'logo' => $tenant->logo,
        ];
    }

    /**
     * Criar tenant com ID específico
     * Usado quando precisamos garantir que o ID não conflite com bancos existentes
     */
    public function criarComId(Tenant $tenant, int $id): Tenant
    {
        try {
            $data = $this->toArray($tenant);
            
            \Log::debug('Criando tenant com ID específico', [
                'id' => $id,
                'razao_social' => $data['razao_social'] ?? null,
                'cnpj' => $data['cnpj'] ?? null,
            ]);
            
            // Verificar se já existe tenant com esse ID
            $existente = TenantModel::find($id);
            if ($existente) {
                throw new \RuntimeException("Já existe um tenant com ID {$id}");
            }
            
            // Ajustar sequência do PostgreSQL para permitir inserir ID específico
            try {
                // Obter o maior ID atual
                $maxId = TenantModel::max('id') ?? 0;
                $nextId = max($id, $maxId + 1);
                
                // Ajustar sequência para o próximo valor ser maior que o ID fornecido
                \Illuminate\Support\Facades\DB::statement(
                    "SELECT setval(pg_get_serial_sequence('tenants', 'id'), {$nextId}, false)"
                );
            } catch (\Exception $seqException) {
                \Log::warning('Erro ao ajustar sequência, continuando mesmo assim', [
                    'error' => $seqException->getMessage(),
                ]);
            }
            
            // Criar tenant com ID específico usando insert() para forçar o ID
            $data['id'] = $id;
            $data['criado_em'] = now();
            $data['atualizado_em'] = now();
            
            // Usar insert() para forçar o ID
            \Illuminate\Support\Facades\DB::table('tenants')->insert($data);
            
            // Buscar o modelo criado
            $model = TenantModel::findOrFail($id);
            
            \Log::info('Tenant criado com ID específico', [
                'tenant_id' => $model->id,
                'razao_social' => $model->razao_social,
            ]);
            
            return $this->toDomain($model);
            
        } catch (\Exception $e) {
            \Log::error('Erro ao criar tenant com ID específico', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Erro ao criar tenant com ID específico: ' . $e->getMessage(), 0, $e);
        }
    }

    public function criar(Tenant $tenant): Tenant
    {
        try {
            $data = $this->toArray($tenant);
            
            \Log::debug('Criando tenant', [
                'data_keys' => array_keys($data),
                'razao_social' => $data['razao_social'] ?? null,
                'cnpj' => $data['cnpj'] ?? null,
            ]);
            
            // Tentar criar usando create() primeiro (método padrão do Eloquent)
            try {
                $model = TenantModel::create($data);
                
                if (!$model) {
                    \Log::error('TenantModel::create() retornou null', [
                        'data' => $data,
                    ]);
                    throw new \RuntimeException('Falha ao criar o tenant no banco de dados. O modelo não foi criado.');
                }
                
                // Verificar se o ID foi gerado imediatamente
                if ($model->id) {
                    \Log::debug('Tenant criado com sucesso (ID gerado imediatamente)', [
                        'tenant_id' => $model->id,
                        'razao_social' => $model->razao_social,
                    ]);
                    return $this->toDomain($model);
                }
                
                // Se não tem ID, NÃO tentar refresh() (vai falhar se não tem ID)
                // Em vez disso, buscar pelo CNPJ ou email imediatamente
                \Log::warning('Tenant criado mas sem ID imediato, buscando pelo CNPJ/email', [
                    'model_exists' => $model->exists,
                    'cnpj' => $data['cnpj'] ?? null,
                    'email' => $data['email'] ?? null,
                ]);
                
                // Tentar buscar pelo CNPJ primeiro (mais único)
                if (!empty($data['cnpj'])) {
                    $foundModel = TenantModel::where('cnpj', $data['cnpj'])->first();
                    if ($foundModel && $foundModel->id) {
                        \Log::debug('Tenant encontrado pelo CNPJ após criação', [
                            'tenant_id' => $foundModel->id,
                            'razao_social' => $foundModel->razao_social,
                        ]);
                        return $this->toDomain($foundModel);
                    }
                }
                
                // Se não encontrou pelo CNPJ, tentar pelo email
                if (!empty($data['email'])) {
                    $foundModel = TenantModel::where('email', $data['email'])->first();
                    if ($foundModel && $foundModel->id) {
                        \Log::debug('Tenant encontrado pelo email após criação', [
                            'tenant_id' => $foundModel->id,
                            'razao_social' => $foundModel->razao_social,
                        ]);
                        return $this->toDomain($foundModel);
                    }
                }
                
                // Se não encontrou nem pelo CNPJ nem pelo email, pode ser que o modelo
                // foi criado mas ainda não está visível (problema de transação ou cache)
                // Tentar buscar pelo último registro criado com os mesmos dados
                $foundModel = TenantModel::where('razao_social', $data['razao_social'])
                    ->where('cnpj', $data['cnpj'] ?? '')
                    ->orderBy('id', 'desc')
                    ->first();
                
                if ($foundModel && $foundModel->id) {
                    \Log::debug('Tenant encontrado pela razão social e CNPJ após criação', [
                        'tenant_id' => $foundModel->id,
                        'razao_social' => $foundModel->razao_social,
                    ]);
                    return $this->toDomain($foundModel);
                }
                
                // Se chegou aqui, realmente não conseguiu encontrar o tenant criado
                \Log::error('Tenant criado mas não foi possível recuperar o ID', [
                    'model_exists' => $model->exists,
                    'model_attributes' => $model->getAttributes(),
                    'data' => $data,
                ]);
                throw new \RuntimeException('Falha ao criar tenant: o ID não foi gerado e não foi possível localizar o registro criado.');
                
            } catch (\Illuminate\Database\QueryException $e) {
                // Se create() falhar, tentar com save() manual
                \Log::warning('TenantModel::create() falhou, tentando com save() manual', [
                    'error' => $e->getMessage(),
                ]);
                
                $model = new TenantModel();
                $model->fill($data);
                $saved = $model->save();
                
                if (!$saved) {
                    \Log::error('TenantModel::save() retornou false', [
                        'data' => $data,
                        'error' => $e->getMessage(),
                    ]);
                    throw new \RuntimeException('Falha ao criar tenant: ' . $e->getMessage(), 0, $e);
                }
                
                // Se save() funcionou mas não tem ID, buscar pelo CNPJ/email
                if (!$model->id) {
                    \Log::warning('Tenant salvo mas sem ID, buscando pelo CNPJ/email', [
                        'cnpj' => $data['cnpj'] ?? null,
                        'email' => $data['email'] ?? null,
                    ]);
                    
                    if (!empty($data['cnpj'])) {
                        $foundModel = TenantModel::where('cnpj', $data['cnpj'])->first();
                        if ($foundModel && $foundModel->id) {
                            return $this->toDomain($foundModel);
                        }
                    }
                    
                    if (!empty($data['email'])) {
                        $foundModel = TenantModel::where('email', $data['email'])->first();
                        if ($foundModel && $foundModel->id) {
                            return $this->toDomain($foundModel);
                        }
                    }
                    
                    throw new \RuntimeException('Falha ao criar tenant: o ID não foi gerado após salvar.');
                }
                
                \Log::debug('Tenant criado com sucesso usando save() manual', [
                    'tenant_id' => $model->id,
                ]);
                
                return $this->toDomain($model);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Erro de banco de dados (constraints, duplicatas, etc.)
            \Log::error('Erro de banco de dados ao criar tenant', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql() ?? null,
                'bindings' => $e->getBindings() ?? null,
            ]);
            throw new \RuntimeException('Erro ao criar tenant no banco de dados: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            // Outros erros
            \Log::error('Erro inesperado ao criar tenant', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Erro inesperado ao criar tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    public function buscarPorId(int $id): ?Tenant
    {
        $model = TenantModel::find($id);
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarPorCnpj(string $cnpj): ?Tenant
    {
        $model = TenantModel::where('cnpj', $cnpj)->first();
        return $model ? $this->toDomain($model) : null;
    }

    public function buscarComFiltros(array $filtros = []): LengthAwarePaginator
    {
        $query = TenantModel::query();

        if (isset($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function($q) use ($search) {
                $q->where('razao_social', 'like', "%{$search}%")
                  ->orWhere('cnpj', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $filtros['per_page'] ?? 15;
        $paginator = $query->orderBy(\App\Database\Schema\Blueprint::CREATED_AT, 'desc')->paginate($perPage);

        // Converter cada item para entidade do domínio
        $paginator->getCollection()->transform(function ($model) {
            return $this->toDomain($model);
        });

        return $paginator;
    }

    public function atualizar(Tenant $tenant): Tenant
    {
        $model = TenantModel::findOrFail($tenant->id);
        $model->update($this->toArray($tenant));
        return $this->toDomain($model->fresh());
    }

    public function deletar(int $id): void
    {
        TenantModel::findOrFail($id)->delete();
    }

    /**
     * Buscar modelo Eloquent por ID
     */
    public function buscarModeloPorId(int $id): ?TenantModel
    {
        return TenantModel::find($id);
    }
}

