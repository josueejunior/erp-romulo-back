<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Empresa;
use App\Modules\Auth\Models\User;
use App\Services\Traits\AuthScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Support\Facades\Log;

class TenantService
{
    use AuthScope;
    /**
     * Gerar ID único para o tenant (usando auto-increment)
     * Não precisa mais gerar slug, o banco vai gerar o ID automaticamente
     */
    public function generateUniqueTenantId(string $razaoSocial): ?int
    {
        // Retornar null para usar auto-increment do banco
        // O Laravel vai gerar o ID automaticamente
        return null;
    }

    /**
     * Preparar dados do tenant a partir dos dados validados
     */
    public function prepareTenantData(array $validated): array
    {
        $tenantData = [
            'razao_social' => $validated['razao_social'],
            'cnpj' => $validated['cnpj'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => $validated['status'] ?? 'ativa',
        ];

        // Adicionar campos opcionais
        $optionalFields = [
            'endereco', 'cidade', 'estado', 'cep', 'telefones', 'emails_adicionais',
            'banco', 'agencia', 'conta', 'tipo_conta', 'pix',
            'representante_legal_nome', 'representante_legal_cpf', 'representante_legal_cargo', 'logo'
        ];

        foreach ($optionalFields as $field) {
            if (isset($validated[$field])) {
                $tenantData[$field] = $validated[$field];
            }
        }

        return $tenantData;
    }

    /**
     * Criar banco de dados do tenant
     */
    public function createTenantDatabase(Tenant $tenant): void
    {
        try {
            CreateDatabase::dispatchSync($tenant);
            MigrateDatabase::dispatchSync($tenant);
        } catch (\Exception $e) {
            Log::error('Erro ao criar banco do tenant', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Erro ao criar o banco de dados da empresa: ' . $e->getMessage());
        }
    }

    /**
     * Inicializar roles e permissões no tenant
     */
    public function initializeTenantRoles(): void
    {
        // Limpar cache de permissões
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Criar roles e permissões
        $seeder = new RolesPermissionsSeeder();
        $seeder->run();

        // Limpar cache novamente
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Criar empresa dentro do tenant
     */
    public function createEmpresa(array $validated): Empresa
    {
        return Empresa::create([
            'razao_social' => $validated['razao_social'],
            'cnpj' => $validated['cnpj'] ?? null,
            'email' => $validated['email'] ?? null,
            'endereco' => $validated['endereco'] ?? null,
            'cidade' => $validated['cidade'] ?? null,
            'estado' => $validated['estado'] ?? null,
            'cep' => $validated['cep'] ?? null,
            'telefones' => $validated['telefones'] ?? null,
            'emails' => $validated['emails_adicionais'] ?? null,
            'banco_nome' => $validated['banco'] ?? null,
            'banco_agencia' => $validated['agencia'] ?? null,
            'banco_conta' => $validated['conta'] ?? null,
            'banco_tipo' => $validated['tipo_conta'] ?? null,
            'banco_pix' => $validated['pix'] ?? null,
            'representante_legal' => $validated['representante_legal_nome'] ?? null,
            'logo' => $validated['logo'] ?? null,
            'status' => $validated['status'] ?? 'ativa',
        ]);
    }

    /**
     * Criar usuário administrador no tenant
     */
    public function createAdminUser(array $adminData, Empresa $empresa): User
    {
        $adminUser = User::create([
            'name' => $adminData['admin_name'],
            'email' => $adminData['admin_email'],
            'password' => Hash::make($adminData['admin_password']),
            'empresa_ativa_id' => $empresa->id,
        ]);

        // Atribuir role de Administrador
        $adminUser->assignRole('Administrador');

        // Associar usuário à empresa
        $adminUser->empresas()->attach($empresa->id, [
            'perfil' => 'administrador'
        ]);

        return $adminUser;
    }

    /**
     * Criar tenant completo com empresa e opcionalmente usuário admin
     */
    public function createTenantWithEmpresa(array $validated, bool $requireAdmin = false): array
    {
        // Preparar dados do tenant (sem definir ID, deixar o banco gerar)
        $tenantData = $this->prepareTenantData($validated);
        // Não definir 'id' - deixar o banco gerar automaticamente com auto-increment

        DB::beginTransaction();

        try {
            // Criar tenant
            $tenant = Tenant::create($tenantData);

            // Criar banco de dados do tenant
            $this->createTenantDatabase($tenant);

            // Inicializar contexto do tenant
            tenancy()->initialize($tenant);
            $adminUser = null;

            try {
                // Inicializar roles e permissões
                $this->initializeTenantRoles();

                // Criar empresa dentro do tenant
                $empresa = $this->createEmpresa($validated);

                // Se dados do admin foram fornecidos, criar usuário administrador
                if (!empty($validated['admin_name']) && 
                    !empty($validated['admin_email']) && 
                    !empty($validated['admin_password'])) {
                    $adminUser = $this->createAdminUser($validated, $empresa);
                } elseif ($requireAdmin) {
                    throw new \Exception('Dados do administrador são obrigatórios.');
                }

                // Finalizar contexto do tenant
                tenancy()->end();

                DB::commit();

                return [
                    'tenant' => $tenant,
                    'empresa' => $empresa,
                    'admin_user' => $adminUser,
                ];

            } catch (\Exception $e) {
                tenancy()->end();
                DB::rollBack();
                
                Log::error('Erro ao criar empresa/usuário no tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                throw new \Exception('Erro ao criar empresa ou usuário administrador: ' . $e->getMessage());
            }

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Buscar tenants com filtros
     */
    public function searchTenants(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Tenant::query();

        // Filtro por status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Busca por razão social, CNPJ ou email
        if (isset($filters['search']) && !empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('razao_social', 'ilike', "%{$search}%")
                  ->orWhere('cnpj', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $perPage = $filters['per_page'] ?? 15;
        
        return $query->orderBy('criado_em', 'desc')->paginate($perPage);
    }

    /**
     * Validar se CNPJ pode ser alterado
     */
    public function canUpdateCnpj(Tenant $tenant, ?string $newCnpj): bool
    {
        // CNPJ não pode ser alterado se já existe um definido
        if ($tenant->cnpj && $newCnpj && $newCnpj !== $tenant->cnpj) {
            return false;
        }
        
        return true;
    }

    /**
     * Inativar tenant
     */
    public function inactivateTenant(Tenant $tenant): Tenant
    {
        if ($tenant->status !== 'inativa') {
            $tenant->status = 'inativa';
            $tenant->save();
        }
        
        return $tenant;
    }

    /**
     * Reativar tenant
     */
    public function reactivateTenant(Tenant $tenant): Tenant
    {
        $tenant->status = 'ativa';
        $tenant->save();
        
        return $tenant;
    }
}

