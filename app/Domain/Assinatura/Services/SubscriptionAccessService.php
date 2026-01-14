<?php

declare(strict_types=1);

namespace App\Domain\Assinatura\Services;

use App\Domain\Assinatura\Entities\Assinatura;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Plano\Entities\Plano as PlanoEntity;
use Illuminate\Support\Facades\Log;

/**
 * Domain Service para validação de acesso a recursos baseado em assinatura
 * 
 * ✅ DDD: Centraliza regras de negócio sobre acesso a recursos/rotas
 * 
 * Responsabilidades:
 * - Verificar se uma rota/recurso pode ser acessada baseado na assinatura
 * - Aplicar exceções especiais (ex: dashboard para onboarding)
 * - Validar acesso baseado no plano e assinatura
 */
final class SubscriptionAccessService
{
    public function __construct(
        private readonly AssinaturaRepositoryInterface $assinaturaRepository,
        private readonly PlanoRepositoryInterface $planoRepository,
        private readonly AssinaturaDomainService $assinaturaDomainService,
    ) {}

    /**
     * Verifica se uma rota pode ser acessada sem validação de assinatura
     * 
     * ✅ DDD: Regra de negócio isolada
     * 
     * @param string $routeName Nome da rota
     * @param string $path Path da requisição
     * @return bool
     */
    public function isRouteExemptFromSubscriptionCheck(string $routeName, string $path): bool
    {
        // Dashboard é acessível para onboarding mesmo sem assinatura ativa
        $isDashboardRoute = $routeName === 'dashboard' 
            || $path === 'api/v1/dashboard' 
            || str_ends_with($path, '/dashboard');
        
        // 🔥 Planos são públicos - podem ser visualizados sem assinatura
        // Importante para a tela de cadastro e escolha de planos funcionar
        $isPlanosRoute = $routeName === 'planos' 
            || $routeName === 'planos.list' 
            || $routeName === 'planos.get'
            || $path === 'api/v1/planos' 
            || preg_match('#^api/v1/planos(/\d+)?$#', $path);
        
        // 🔥 Onboarding deve ser acessível mesmo sem assinatura (para permitir tutorial)
        $isOnboardingRoute = $routeName === 'onboarding.*'
            || str_starts_with($routeName, 'onboarding.')
            || $path === 'api/v1/onboarding/status'
            || $path === 'api/v1/onboarding/concluir'
            || $path === 'api/v1/onboarding/marcar-etapa'
            || preg_match('#^api/v1/onboarding/#', $path);
        
        $isExempt = $isDashboardRoute || $isPlanosRoute || $isOnboardingRoute;
        
        Log::info('🔍 SubscriptionAccessService::isRouteExemptFromSubscriptionCheck', [
            'route_name' => $routeName,
            'path' => $path,
            'is_dashboard_route' => $isDashboardRoute,
            'is_planos_route' => $isPlanosRoute,
            'is_onboarding_route' => $isOnboardingRoute ?? false,
            'is_exempt' => $isExempt,
        ]);
        
        return $isExempt;
    }

    /**
     * Verifica se o dashboard pode ser acessado (incluindo exceção para onboarding)
     * 
     * ✅ DDD: Regra de negócio sobre acesso ao dashboard
     * 
     * @param int|null $empresaId ID da empresa
     * @param Assinatura|null $assinatura Assinatura atual (opcional, para evitar busca extra)
     * @return bool
     */
    public function podeAcessarDashboard(?int $empresaId, ?Assinatura $assinatura = null): bool
    {
        // Se não tem empresa, não pode acessar
        if (!$empresaId) {
            return false;
        }

        // Buscar assinatura se não foi fornecida
        if (!$assinatura) {
            $assinatura = $this->assinaturaRepository->buscarAssinaturaAtualPorEmpresa($empresaId);
        }

        // Se não tem assinatura, permitir acesso (para onboarding)
        if (!$assinatura) {
            return true;
        }

        // Buscar plano (entidade)
        $planoEntity = $this->planoRepository->buscarPorId($assinatura->planoId);
        if (!$planoEntity) {
            return false;
        }

        // 🔥 REGRA DE NEGÓCIO: Dashboard é acessível para planos gratuitos (onboarding)
        // Verificar se é plano gratuito (verificação direta na entidade)
        $isPlanoGratuito = !$planoEntity->precoMensal || $planoEntity->precoMensal == 0;
        if ($isPlanoGratuito) {
            return true;
        }

        // 🔥 CORREÇÃO: Para planos PAGOS, o dashboard é sempre acessível
        // O dashboard é uma funcionalidade básica disponível para todos os planos pagos
        // A verificação de recursos específicos (relatorios, dashboard_analytics) foi removida
        // porque o dashboard é uma funcionalidade core do sistema
        return true;
    }

}

