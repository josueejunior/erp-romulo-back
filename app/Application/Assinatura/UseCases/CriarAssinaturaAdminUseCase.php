<?php

declare(strict_types=1);

namespace App\Application\Assinatura\UseCases;

use App\Application\Assinatura\DTOs\CriarAssinaturaDTO;
use App\Domain\Assinatura\Entities\Assinatura;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Assinatura\Services\AssinaturaDomainService;
use App\Domain\Assinatura\Events\AssinaturaCriada;
use App\Domain\Shared\Events\EventDispatcherInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Services\AdminTenancyRunner;
use App\Models\TenantEmpresa;
use App\Models\Empresa;
use App\Modules\Auth\Models\User;
use App\Modules\Assinatura\Models\Plano as PlanoModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Use Case: Criar Assinatura no Painel Admin
 * 
 * Permite ao admin criar assinaturas para qualquer tenant/empresa
 * 
 * 🔥 ARQUITETURA LIMPA:
 * - Usa AdminTenancyRunner para gerenciar contexto de tenancy
 * - Usa Domain Services para regras de negócio
 * - Isola lógica de infraestrutura
 */
class CriarAssinaturaAdminUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
        private TenantRepositoryInterface $tenantRepository,
        private PlanoRepositoryInterface $planoRepository,
        private AssinaturaDomainService $assinaturaDomainService,
        private AdminTenancyRunner $adminTenancyRunner,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Executa o caso de uso
     * 
     * @param int $tenantId ID do tenant
     * @param array $dados Dados da assinatura (plano_id, empresa_id, user_id, etc)
     * @return Assinatura Entidade criada
     * @throws DomainException Se houver erro de validação ou regra de negócio
     */
    public function executar(int $tenantId, array $dados): Assinatura
    {
        // Validar que o tenant existe
        $tenantDomain = $this->tenantRepository->buscarPorId($tenantId);
        if (!$tenantDomain) {
            throw new DomainException('Tenant não encontrado.');
        }

        // Validar que o plano existe
        // Converter para int para evitar TypeError (pode vir como string do request)
        $planoId = is_int($dados['plano_id']) ? $dados['plano_id'] : (int) $dados['plano_id'];
        $planoDomain = $this->planoRepository->buscarPorId($planoId);
        if (!$planoDomain) {
            throw new DomainException('Plano não encontrado.');
        }

        // Buscar empresa dentro do tenant
        $empresaId = $dados['empresa_id'] ?? null;
        if (!$empresaId) {
            // Se não fornecido, buscar primeira empresa do tenant
            $empresaId = $this->adminTenancyRunner->runForTenant($tenantDomain, function () {
                $tenantEmpresa = TenantEmpresa::where('tenant_id', tenancy()->tenant->id)->first();
                if ($tenantEmpresa) {
                    $empresa = Empresa::find($tenantEmpresa->empresa_id);
                    return $empresa?->id;
                }
                // Fallback: primeira empresa do tenant
                $empresa = Empresa::first();
                return $empresa?->id;
            });

            if (!$empresaId) {
                throw new DomainException('Nenhuma empresa encontrada para este tenant. Crie uma empresa primeiro.');
            }
        } else {
            // Validar que a empresa existe no tenant
            $empresaExiste = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($empresaId) {
                return Empresa::find($empresaId) !== null;
            });

            if (!$empresaExiste) {
                throw new DomainException('Empresa não encontrada neste tenant.');
            }
        }

        // Buscar usuário dentro do tenant (opcional, mas recomendado)
        $userId = $dados['user_id'] ?? null;
        if (!$userId) {
            // Se não fornecido, buscar primeiro admin do tenant
            $userId = $this->adminTenancyRunner->runForTenant($tenantDomain, function () {
                $user = User::role('Administrador')->first();
                return $user?->id;
            });

            if (!$userId) {
                throw new DomainException('Nenhum usuário administrador encontrado para este tenant. Crie um usuário primeiro.');
            }
        } else {
            // Validar que o usuário existe no tenant
            $userExiste = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($userId) {
                return User::find($userId) !== null;
            });

            if (!$userExiste) {
                throw new DomainException('Usuário não encontrado neste tenant.');
            }
        }

        // Calcular datas
        $dataInicio = isset($dados['data_inicio']) 
            ? Carbon::parse($dados['data_inicio']) 
            : Carbon::now();
        
        // Se data_fim não foi fornecida, calcular usando Domain Service
        if (isset($dados['data_fim'])) {
            $dataFim = Carbon::parse($dados['data_fim']);
        } else {
            // Buscar modelo Plano para usar no Domain Service
            // Usar $planoId já convertido para int
            $planoModel = PlanoModel::find($planoId);
            if (!$planoModel) {
                throw new DomainException('Plano não encontrado.');
            }
            
            // Usar Domain Service para calcular data fim
            $periodo = $dados['periodo'] ?? 'mensal';
            $dataFim = $this->assinaturaDomainService->calcularDataFim($planoModel, $periodo, $dataInicio);
        }

        // 🔥 CRÍTICO: Sempre usar o valor do plano, não permitir valor manual diferente
        // O valor pago DEVE ser o valor da mensalidade do plano
        $valorPago = $planoDomain->precoMensal ?? 0;
        
        // Se foi fornecido valor_pago manualmente, usar apenas se for igual ao do plano (validação)
        if (isset($dados['valor_pago']) && $dados['valor_pago'] > 0) {
            if (abs($dados['valor_pago'] - $valorPago) > 0.01) {
                Log::warning('CriarAssinaturaAdminUseCase - Valor pago fornecido difere do plano, usando valor do plano', [
                    'valor_fornecido' => $dados['valor_pago'],
                    'valor_plano' => $valorPago,
                ]);
            }
            // Mesmo assim, usar valor do plano para garantir consistência
        }

        // Preparar DTO
        $dto = new CriarAssinaturaDTO(
            userId: $userId,
            planoId: $planoId,
            status: $dados['status'] ?? 'ativa',
            dataInicio: $dataInicio,
            dataFim: $dataFim,
            valorPago: $valorPago, // Sempre usar valor do plano
            metodoPagamento: $dados['metodo_pagamento'] ?? 'gratuito',
            transacaoId: $dados['transacao_id'] ?? null,
            diasGracePeriod: $dados['dias_grace_period'] ?? 7,
            observacoes: $dados['observacoes'] ?? 'Criada pelo painel administrativo',
            tenantId: $tenantId,
            empresaId: $empresaId,
        );

        // Criar assinatura dentro do contexto do tenant
        $assinatura = $this->adminTenancyRunner->runForTenant($tenantDomain, function () use ($dto) {
            $criarAssinaturaUseCase = app(\App\Application\Assinatura\UseCases\CriarAssinaturaUseCase::class);
            return $criarAssinaturaUseCase->executar($dto);
        });

        Log::info('CriarAssinaturaAdminUseCase - Assinatura criada com sucesso', [
            'tenant_id' => $tenantId,
            'empresa_id' => $empresaId,
            'user_id' => $userId,
            'plano_id' => $planoId,
            'assinatura_id' => $assinatura->id,
        ]);

        // Nota: O evento AssinaturaCriada já é disparado pelo CriarAssinaturaUseCase
        // Não é necessário disparar novamente aqui para evitar duplicação

        // 🔥 PERFORMANCE: Invalidar cache de listagem de assinaturas
        \App\Application\Assinatura\UseCases\ListarAssinaturasAdminUseCase::invalidarCache();

        return $assinatura;
    }
}

