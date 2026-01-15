<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant as TenantModel;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Tenant\Services\TenantDatabaseServiceInterface;
use App\Domain\Tenant\Services\TenantRolesServiceInterface;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Application\Tenant\DTOs\CriarTenantDTO;
use App\Domain\Tenant\Events\EmpresaCriada;
use App\Domain\Shared\Events\EventDispatcherInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Job para configurar tenant (criar banco, migrations, empresa, usuário)
 * 
 * Este job processa a criação completa do tenant de forma assíncrona,
 * evitando timeouts e garantindo resiliência com retries automáticos.
 */
class SetupTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de tentativas antes de marcar como falha
     */
    public int $tries = 3;

    /**
     * Backoff exponencial entre tentativas (em segundos)
     */
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min

    /**
     * Timeout para execução do job (em segundos)
     */
    public int $timeout = 600; // 10 minutos

    /**
     * @param int $tenantId ID do tenant a ser configurado
     * @param array $dtoData Dados do DTO serializados (para criar empresa e usuário)
     */
    public function __construct(
        private readonly int $tenantId,
        private readonly array $dtoData,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        TenantRepositoryInterface $tenantRepository,
        TenantDatabaseServiceInterface $databaseService,
        TenantRolesServiceInterface $rolesService,
        EmpresaRepositoryInterface $empresaRepository,
        UserRepositoryInterface $userRepository,
        EventDispatcherInterface $eventDispatcher,
    ): void {
        Log::info('SetupTenantJob iniciado', [
            'tenant_id' => $this->tenantId,
        ]);

        // 1. Buscar tenant
        $tenantDomain = $tenantRepository->buscarPorId($this->tenantId);
        if (!$tenantDomain) {
            throw new \RuntimeException("Tenant {$this->tenantId} não encontrado.");
        }

        $tenantModel = $tenantRepository->buscarModeloPorId($this->tenantId);
        if (!$tenantModel) {
            throw new \RuntimeException("Tenant model {$this->tenantId} não encontrado.");
        }

        // 2. Atualizar status para 'processing'
        try {
            $tenantDomain = $tenantDomain->withUpdates(['status' => 'processing']);
            $tenantRepository->atualizar($tenantDomain);
            
            Log::info('SetupTenantJob - Status atualizado para processing', [
                'tenant_id' => $this->tenantId,
            ]);
        } catch (\Exception $e) {
            Log::error('SetupTenantJob - Erro ao atualizar status para processing', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
            // Continuar mesmo se falhar (pode ser que o status já esteja correto)
        }

        // 🔥 ARQUITETURA SINGLE DATABASE:
        // Criar banco e executar migrations apenas se TENANCY_CREATE_DATABASES=true
        if (env('TENANCY_CREATE_DATABASES', false)) {
            try {
                // 3. Criar banco de dados do tenant
                Log::info('SetupTenantJob - Criando banco de dados', [
                    'tenant_id' => $this->tenantId,
                ]);
                
                $databaseService->criarBancoDados($tenantDomain);

                // 4. Executar migrations
                Log::info('SetupTenantJob - Executando migrations', [
                    'tenant_id' => $this->tenantId,
                ]);
                
                $databaseService->executarMigrations($tenantDomain);
            } catch (\Exception $e) {
                Log::error('SetupTenantJob - Erro ao criar banco/migrations do tenant', [
                    'tenant_id' => $this->tenantId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        } else {
            Log::info('SetupTenantJob - Criação de banco desabilitada (Single Database Tenancy)', [
                'tenant_id' => $this->tenantId,
                'arquitetura' => 'Single Database - isolamento por empresa_id',
            ]);
        }
        
        // Continuar com inicialização do contexto do tenant mesmo sem banco separado
        try {

            // 5. Recarregar tenant model para garantir que está atualizado
            $tenantModel = $tenantRepository->buscarModeloPorId($this->tenantId);
            if (!$tenantModel) {
                throw new \RuntimeException("Tenant model {$this->tenantId} não encontrado após criar banco.");
            }

            // 5/6. Recarregar tenant model para garantir que está atualizado
            $tenantModel = $tenantRepository->buscarModeloPorId($this->tenantId);
            if (!$tenantModel) {
                throw new \RuntimeException("Tenant model {$this->tenantId} não encontrado.");
            }

            // 6/7. Inicializar contexto do tenant
            tenancy()->initialize($tenantModel);

            try {
                // 7. Inicializar roles e permissões
                Log::info('SetupTenantJob - Inicializando roles', [
                    'tenant_id' => $this->tenantId,
                ]);
                
                $rolesService->inicializarRoles($tenantDomain);

                // 8. Recriar DTO a partir dos dados serializados
                $dto = CriarTenantDTO::fromArray($this->dtoData);

                // 9. Criar empresa dentro do tenant
                Log::info('SetupTenantJob - Criando empresa', [
                    'tenant_id' => $this->tenantId,
                ]);
                
                $empresa = $empresaRepository->criarNoTenant($this->tenantId, $dto);

                // 10. Criar usuário administrador (se fornecido)
                $adminUser = null;
                if ($dto->temDadosAdmin()) {
                    Log::info('SetupTenantJob - Criando usuário administrador', [
                        'tenant_id' => $this->tenantId,
                        'admin_email' => $dto->adminEmail,
                    ]);
                    
                    $adminUser = $userRepository->criarAdministrador(
                        tenantId: $this->tenantId,
                        empresaId: $empresa->id,
                        nome: $dto->adminName,
                        email: $dto->adminEmail,
                        senha: $dto->adminPassword,
                    );
                }

                // 11. Finalizar contexto do tenant
                tenancy()->end();

                // 12. Atualizar status para 'ativa'
                $tenantDomain = $tenantDomain->withUpdates(['status' => 'ativa']);
                $tenantRepository->atualizar($tenantDomain);

                // 13. Disparar evento EmpresaCriada para enviar email de boas-vindas
                try {
                    $eventDispatcher->dispatch(
                        new EmpresaCriada(
                            tenantId: $this->tenantId,
                            razaoSocial: $tenantDomain->razaoSocial,
                            cnpj: $tenantDomain->cnpj,
                            email: $dto->email ?? $dto->adminEmail,
                            empresaId: $empresa->id,
                            userId: $adminUser?->id ?? null,
                        )
                    );
                    
                    Log::info('SetupTenantJob - Evento EmpresaCriada disparado', [
                        'tenant_id' => $this->tenantId,
                        'empresa_id' => $empresa->id,
                    ]);
                } catch (\Exception $eventException) {
                    // Não quebrar o fluxo se houver erro ao disparar evento
                    Log::warning('SetupTenantJob - Erro ao disparar evento EmpresaCriada', [
                        'tenant_id' => $this->tenantId,
                        'error' => $eventException->getMessage(),
                    ]);
                }

                Log::info('SetupTenantJob - Concluído com sucesso', [
                    'tenant_id' => $this->tenantId,
                    'empresa_id' => $empresa->id ?? null,
                ]);

            } catch (\Exception $e) {
                tenancy()->end();
                throw $e; // Relançar para ser capturado pelo catch externo
            }

        } catch (\Exception $e) {
            Log::error('SetupTenantJob - Erro durante processamento', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Marcar status como 'failed' (será feito no método failed())
            throw $e; // Relançar para Laravel fazer retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('SetupTenantJob - Falhou após todas as tentativas', [
            'tenant_id' => $this->tenantId,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);

        try {
            // Marcar tenant como 'failed'
            $tenantRepository = app(TenantRepositoryInterface::class);
            $tenantDomain = $tenantRepository->buscarPorId($this->tenantId);
            
            if ($tenantDomain) {
                $tenantDomain = $tenantDomain->withUpdates(['status' => 'failed']);
                $tenantRepository->atualizar($tenantDomain);
                
                Log::info('SetupTenantJob - Status atualizado para failed', [
                    'tenant_id' => $this->tenantId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SetupTenantJob - Erro ao marcar tenant como failed', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
        }

        // TODO: Aqui você poderia notificar um admin ou criar um alerta
    }
}

