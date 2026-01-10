<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Empresa;
use App\Modules\Auth\Models\User;
use App\Services\TenantService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Database\Seeders\RolesPermissionsSeeder;

/**
 * Seeder para cadastrar empresas clientes reais
 * 
 * Empresas:
 * 1. CAMARGO REPRESENTAÇÕES
 * 2. Rosa Vendas Públicas
 */
class EmpresasClientesSeeder extends Seeder
{
    public function __construct(
        private TenantService $tenantService
    ) {}

    public function run(): void
    {
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('Iniciando cadastro de empresas clientes...');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('');

        // Garantir que roles e permissões existam (se não existirem)
        $this->call(RolesPermissionsSeeder::class);

        // Empresa 1: CAMARGO REPRESENTAÇÕES
        $this->criarEmpresaCamargo();

        $this->command->info('');

        // Empresa 2: Rosa Vendas Públicas
        $this->criarEmpresaRosa();

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('Cadastro de empresas concluído!');
        $this->command->info('═══════════════════════════════════════════════════════');
    }

    /**
     * Criar empresa: CAMARGO REPRESENTAÇÕES
     */
    private function criarEmpresaCamargo(): void
    {
        $this->command->info('📋 Criando empresa: CAMARGO REPRESENTAÇÕES...');

        $cnpj = '63.518.288/0001-00';
        $email = 'camargo.representacoesbr@gmail.com';

        // Verificar se tenant já existe
        $tenant = Tenant::where('cnpj', $cnpj)->first();

        if ($tenant) {
            $this->command->warn("⚠️  Tenant já existe para CNPJ: {$cnpj}");
            $this->command->info("   Tenant ID: {$tenant->id}");
            $this->command->info("   Razão Social: {$tenant->razao_social}");
            
            // Verificar se empresa e usuário já existem no tenant
            tenancy()->initialize($tenant);
            $empresa = \App\Models\Empresa::where('cnpj', $cnpj)->first();
            $adminUser = \App\Modules\Auth\Models\User::where('email', $email)->first();
            tenancy()->end();
            
            if ($empresa && $adminUser) {
                $this->command->info("   ✅ Empresa e usuário já existem no tenant.");
                return;
            }
            
            // Se tenant existe mas empresa/usuário não, criar apenas eles
            $this->command->info("   Criando empresa e usuário no tenant existente...");
            $this->criarEmpresaEUsuarioNoTenant($tenant, [
                'razao_social' => '36.518.288 CAMARGO REPRESENTAÇÕES',
                'nome_fantasia' => 'CAMARGO REPRESENTAÇÕES',
                'cnpj' => $cnpj,
                'endereco' => 'Rua Manoel Caires de Souza, 320, Perequê Mirim',
                'cidade' => 'Caraguatatuba',
                'estado' => 'SP',
                'cep' => '11668-342',
                'telefones' => ['(12) 988928512'],
                'email' => $email,
                'status' => 'ativa',
                'admin_name' => 'Administrador Camargo',
                'admin_email' => $email,
                'admin_password' => 'dadsd222223!!@ffewFDDF',
            ]);
            return;
        }

        try {
            // Preparar dados do tenant e empresa
            $dados = [
                'razao_social' => '36.518.288 CAMARGO REPRESENTAÇÕES',
                'nome_fantasia' => 'CAMARGO REPRESENTAÇÕES',
                'cnpj' => $cnpj,
                'endereco' => 'Rua Manoel Caires de Souza, 320, Perequê Mirim',
                'cidade' => 'Caraguatatuba',
                'estado' => 'SP',
                'cep' => '11668-342',
                'telefones' => ['(12) 988928512'],
                'email' => $email,
                'status' => 'ativa',
                // Dados do administrador
                'admin_name' => 'Administrador Camargo',
                'admin_email' => $email,
                'admin_password' => 'dadsd222223!!@ffewFDDF',
            ];

            $this->command->info('   Criando tenant e banco de dados...');
            
            // Criar tenant com empresa e usuário usando método direto (similar ao DatabaseSeeder)
            $resultado = $this->criarTenantCompleto($dados);

            $tenant = $resultado['tenant'];
            $empresa = $resultado['empresa'];
            $adminUser = $resultado['admin_user'];

            // Atualizar empresa com dados adicionais (endereço detalhado)
            tenancy()->initialize($tenant);
            $empresa->update([
                'nome_fantasia' => 'CAMARGO REPRESENTAÇÕES',
                'logradouro' => 'Rua Manoel Caires de Souza',
                'numero' => '320',
                'bairro' => 'Perequê Mirim',
                'complemento' => null,
            ]);
            tenancy()->end();

            $this->command->info("✅ Empresa criada com sucesso!");
            $this->command->info("   Tenant ID: {$tenant->id}");
            $this->command->info("   Empresa ID: {$empresa->id}");
            $this->command->info("   Razão Social: {$empresa->razao_social}");
            $this->command->info("   Email: {$email}");
            $this->command->info("   Usuário Admin: {$adminUser->email}");

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->command->error("❌ Erro ao criar empresa CAMARGO REPRESENTAÇÕES:");
            $this->command->error("   Tenant não encontrado após criação. Possível problema de transação ou conexão.");
            $this->command->error("   Detalhes: {$e->getMessage()}");
            Log::error('Erro ao criar empresa CAMARGO REPRESENTAÇÕES - Tenant não encontrado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'cnpj' => $cnpj,
            ]);
        } catch (\Exception $e) {
            $this->command->error("❌ Erro ao criar empresa CAMARGO REPRESENTAÇÕES:");
            $this->command->error("   {$e->getMessage()}");
            $this->command->error("   Tipo: " . get_class($e));
            Log::error('Erro ao criar empresa CAMARGO REPRESENTAÇÕES', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'cnpj' => $cnpj,
            ]);
        }
    }

    /**
     * Criar empresa: Rosa Vendas Públicas
     */
    private function criarEmpresaRosa(): void
    {
        $this->command->info('📋 Criando empresa: Rosa Vendas Públicas...');

        $cnpj = '60.920.490/0001-76';
        $email = 'rosavendaspublicas@gmail.com';

        // Verificar se tenant já existe
        $tenant = Tenant::where('cnpj', $cnpj)->first();

        if ($tenant) {
            $this->command->warn("⚠️  Tenant já existe para CNPJ: {$cnpj}");
            $this->command->info("   Tenant ID: {$tenant->id}");
            $this->command->info("   Razão Social: {$tenant->razao_social}");
            
            // Verificar se empresa e usuário já existem no tenant
            tenancy()->initialize($tenant);
            $empresa = \App\Models\Empresa::where('cnpj', $cnpj)->first();
            $adminUser = \App\Modules\Auth\Models\User::where('email', $email)->first();
            tenancy()->end();
            
            if ($empresa && $adminUser) {
                $this->command->info("   ✅ Empresa e usuário já existem no tenant.");
                return;
            }
            
            // Se tenant existe mas empresa/usuário não, criar apenas eles
            $senhaTemporaria = 'RosaVendas2026!@#';
            $this->command->info("   Criando empresa e usuário no tenant existente...");
            $this->criarEmpresaEUsuarioNoTenant($tenant, [
                'razao_social' => '60.920.490 SILVIA GOMES DE CAMPOS DA ROSA',
                'nome_fantasia' => 'Rosa Vendas Públicas',
                'cnpj' => $cnpj,
                'endereco' => 'R RUDI SCHALY, 480, PARQUE SANTA FÉ',
                'cidade' => 'Porto Alegre',
                'estado' => 'RS',
                'cep' => '91.180-380',
                'telefones' => ['(12) 988928512', '(41) 84246889'],
                'email' => $email,
                'status' => 'ativa',
                'admin_name' => 'Administrador Rosa',
                'admin_email' => $email,
                'admin_password' => $senhaTemporaria,
            ]);
            return;
        }

        try {
            // Preparar dados do tenant e empresa
            // Nota: Não foi fornecida senha para esta empresa, vamos gerar uma temporária
            $senhaTemporaria = 'RosaVendas2026!@#';

            $dados = [
                'razao_social' => '60.920.490 SILVIA GOMES DE CAMPOS DA ROSA',
                'nome_fantasia' => 'Rosa Vendas Públicas',
                'cnpj' => $cnpj,
                'endereco' => 'R RUDI SCHALY, 480, PARQUE SANTA FÉ',
                'cidade' => 'Porto Alegre',
                'estado' => 'RS',
                'cep' => '91.180-380',
                'telefones' => ['(12) 988928512', '(41) 84246889'],
                'email' => $email,
                'status' => 'ativa',
                // Dados do administrador (senha temporária - deve ser alterada no primeiro login)
                'admin_name' => 'Administrador Rosa',
                'admin_email' => $email,
                'admin_password' => $senhaTemporaria,
            ];

            $this->command->info('   Criando tenant e banco de dados...');
            
            // Criar tenant com empresa e usuário usando método direto (similar ao DatabaseSeeder)
            $resultado = $this->criarTenantCompleto($dados);

            $tenant = $resultado['tenant'];
            $empresa = $resultado['empresa'];
            $adminUser = $resultado['admin_user'];

            // Inicializar contexto do tenant para criar roles
            tenancy()->initialize($tenant);

            // Garantir que roles existam
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $this->call(RolesPermissionsSeeder::class);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            // Atualizar empresa com dados adicionais
            // Nota: Inscrição Estadual (800/4892572) deve ser adicionada manualmente 
            // se o campo existir no sistema
            $empresa->update([
                'nome_fantasia' => 'Rosa Vendas Públicas',
                'logradouro' => 'R RUDI SCHALY',
                'numero' => '480',
                'bairro' => 'PARQUE SANTA FÉ',
                'complemento' => null,
            ]);

            // Atribuir role de Administrador ao usuário (se ainda não tiver)
            if ($adminUser && !$adminUser->hasRole('Administrador')) {
                $adminUser->assignRole('Administrador');
            }

            // Associar usuário à empresa (se ainda não estiver associado)
            if ($adminUser && !$adminUser->empresas->contains($empresa->id)) {
                $adminUser->empresas()->attach($empresa->id, [
                    'perfil' => 'administrador'
                ]);
            }

            tenancy()->end();

            $this->command->info("✅ Empresa criada com sucesso!");
            $this->command->info("   Tenant ID: {$tenant->id}");
            $this->command->info("   Empresa ID: {$empresa->id}");
            $this->command->info("   Razão Social: {$empresa->razao_social}");
            $this->command->info("   Email: {$email}");
            $this->command->info("   Usuário Admin: {$adminUser->email}");
            $this->command->warn("   ⚠️  SENHA TEMPORÁRIA: {$senhaTemporaria}");
            $this->command->warn("   ⚠️  IMPORTANTE: Solicitar ao cliente que altere a senha no primeiro login!");

        } catch (\Exception $e) {
            $this->command->error("❌ Erro ao criar empresa Rosa Vendas Públicas:");
            $this->command->error("   {$e->getMessage()}");
            Log::error('Erro ao criar empresa Rosa Vendas Públicas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Criar tenant completo usando método direto (similar ao DatabaseSeeder)
     * Isso evita problemas com o TenantService que pode não encontrar o tenant após criação
     */
    private function criarTenantCompleto(array $dados, ?string $senhaTemporaria = null): array
    {
        $cnpj = $dados['cnpj'];
        
        // Criar tenant no banco central
        $tenant = Tenant::create([
            'razao_social' => $dados['razao_social'],
            'cnpj' => $cnpj,
            'email' => $dados['email'],
            'status' => $dados['status'] ?? 'ativa',
            'endereco' => $dados['endereco'] ?? null,
            'cidade' => $dados['cidade'] ?? null,
            'estado' => $dados['estado'] ?? null,
            'cep' => $dados['cep'] ?? null,
            'telefones' => $dados['telefones'] ?? null,
        ]);

        $this->command->info("   Tenant criado (ID: {$tenant->id})");

        // Recarregar para garantir que está persistido
        $tenant->refresh();

        // Criar banco de dados do tenant
        try {
            // Tentar método direto primeiro
            $tenant->database()->manager()->createDatabase($tenant);
            $this->command->info('   Banco de dados criado');
            
            // Executar migrations
            tenancy()->initialize($tenant);
            \Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true
            ]);
            $this->command->info('   Migrations executadas');
        } catch (\Exception $e) {
            // Se falhar, tentar usar os jobs
            try {
                $this->command->warn('   Tentando método alternativo...');
                CreateDatabase::dispatchSync($tenant);
                MigrateDatabase::dispatchSync($tenant);
                $this->command->info('   Banco criado (método alternativo)');
            } catch (\Exception $e2) {
                // Se ainda falhar, deletar tenant criado
                $tenant->delete();
                throw new \Exception('Erro ao criar banco do tenant: ' . $e2->getMessage());
            }
        }

        // Garantir que estamos no contexto do tenant
        if (!tenancy()->initialized) {
            tenancy()->initialize($tenant);
        }

        // Criar roles e permissões
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->call(RolesPermissionsSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Criar empresa dentro do tenant
        $empresa = Empresa::create([
            'razao_social' => $dados['razao_social'],
            'nome_fantasia' => $dados['nome_fantasia'] ?? null,
            'cnpj' => $cnpj,
            'email' => $dados['email'],
            'logradouro' => $dados['endereco'] ?? null,
            'cidade' => $dados['cidade'] ?? null,
            'estado' => $dados['estado'] ?? null,
            'cep' => $dados['cep'] ?? null,
            'telefones' => $dados['telefones'] ?? null,
            'status' => $dados['status'] ?? 'ativa',
        ]);

        // Atualizar com dados adicionais se necessário
        if (isset($dados['nome_fantasia'])) {
            $empresa->update([
                'nome_fantasia' => $dados['nome_fantasia'],
            ]);
        }

        // Criar mapeamento tenant-empresa
        try {
            \App\Models\TenantEmpresa::createOrUpdateMapping($tenant->id, $empresa->id);
        } catch (\Exception $e) {
            Log::warning('Erro ao criar mapeamento tenant-empresa', [
                'tenant_id' => $tenant->id,
                'empresa_id' => $empresa->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Criar usuário administrador
        $adminUser = null;
        if (!empty($dados['admin_name']) && !empty($dados['admin_email']) && !empty($dados['admin_password'])) {
            $adminUser = User::create([
                'name' => $dados['admin_name'],
                'email' => $dados['admin_email'],
                'password' => Hash::make($dados['admin_password']),
                'empresa_ativa_id' => $empresa->id,
            ]);

            // Atribuir role de Administrador
            $adminUser->assignRole('Administrador');

            // Associar usuário à empresa
            $adminUser->empresas()->attach($empresa->id, [
                'perfil' => 'administrador'
            ]);
        }

        tenancy()->end();

        return [
            'tenant' => $tenant,
            'empresa' => $empresa,
            'admin_user' => $adminUser,
        ];
    }

    /**
     * Criar empresa e usuário em um tenant existente
     */
    private function criarEmpresaEUsuarioNoTenant(Tenant $tenant, array $dados): void
    {
        tenancy()->initialize($tenant);

        try {
            // Garantir que roles existam
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $this->call(RolesPermissionsSeeder::class);
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            // Verificar se empresa já existe
            $empresa = Empresa::where('cnpj', $dados['cnpj'])->first();
            if (!$empresa) {
                $empresa = Empresa::create([
                    'razao_social' => $dados['razao_social'],
                    'nome_fantasia' => $dados['nome_fantasia'] ?? null,
                    'cnpj' => $dados['cnpj'],
                    'email' => $dados['email'],
                    'logradouro' => $dados['endereco'] ?? null,
                    'cidade' => $dados['cidade'] ?? null,
                    'estado' => $dados['estado'] ?? null,
                    'cep' => $dados['cep'] ?? null,
                    'telefones' => $dados['telefones'] ?? null,
                    'status' => $dados['status'] ?? 'ativa',
                ]);
            }

            // Verificar se usuário já existe
            $adminUser = User::where('email', $dados['admin_email'])->first();
            if (!$adminUser && !empty($dados['admin_name']) && !empty($dados['admin_email']) && !empty($dados['admin_password'])) {
                $adminUser = User::create([
                    'name' => $dados['admin_name'],
                    'email' => $dados['admin_email'],
                    'password' => Hash::make($dados['admin_password']),
                    'empresa_ativa_id' => $empresa->id,
                ]);

                $adminUser->assignRole('Administrador');
                $adminUser->empresas()->attach($empresa->id, [
                    'perfil' => 'administrador'
                ]);
            }

            $this->command->info("✅ Empresa e usuário criados no tenant existente!");
            if ($empresa) {
                $this->command->info("   Empresa ID: {$empresa->id}");
            }
            if ($adminUser) {
                $this->command->info("   Usuário Admin: {$adminUser->email}");
            }
        } finally {
            tenancy()->end();
        }
    }
}

