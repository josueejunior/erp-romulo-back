<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Empresa;
use App\Modules\Auth\Models\User;
use App\Services\TenantService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;
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

            // Criar tenant com empresa e usuário
            $resultado = $this->tenantService->createTenantWithEmpresa($dados, true);

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
            // Nota: Inscrição Estadual (254.407.129.110) e Municipal (000034747) 
            // devem ser adicionadas manualmente se o campo existir no sistema
            $empresa->update([
                'nome_fantasia' => 'CAMARGO REPRESENTAÇÕES',
                'logradouro' => 'Rua Manoel Caires de Souza',
                'numero' => '320',
                'bairro' => 'Perequê Mirim',
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

        } catch (\Exception $e) {
            $this->command->error("❌ Erro ao criar empresa CAMARGO REPRESENTAÇÕES:");
            $this->command->error("   {$e->getMessage()}");
            Log::error('Erro ao criar empresa CAMARGO REPRESENTAÇÕES', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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

            // Criar tenant com empresa e usuário
            $resultado = $this->tenantService->createTenantWithEmpresa($dados, true);

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
}

