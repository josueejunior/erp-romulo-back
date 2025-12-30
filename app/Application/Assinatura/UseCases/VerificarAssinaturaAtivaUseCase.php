<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use Carbon\Carbon;

/**
 * Use Case: Verificar se a assinatura está ativa e permitir acesso
 * 
 * Retorna informações sobre o status da assinatura para o middleware
 */
class VerificarAssinaturaAtivaUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
    ) {}

    /**
     * Executa o caso de uso
     * 
     * @param int $tenantId ID do tenant
     * @return array Array com informações sobre a assinatura e se pode acessar
     */
    public function executar(int $tenantId): array
    {
        try {
            $assinaturaDomain = $this->assinaturaRepository->buscarAssinaturaAtual($tenantId);
            
            if (!$assinaturaDomain) {
                return [
                    'pode_acessar' => false,
                    'code' => 'NO_SUBSCRIPTION',
                    'message' => 'Nenhuma assinatura encontrada. Contrate um plano para continuar usando o sistema.',
                    'action' => 'subscribe',
                ];
            }

            // Verificar status
            $hoje = Carbon::now();
            $dataFim = $assinaturaDomain->dataFim;
            
            if (!$dataFim) {
                return [
                    'pode_acessar' => false,
                    'code' => 'INVALID_SUBSCRIPTION',
                    'message' => 'Assinatura com data de término inválida.',
                    'action' => 'contact_support',
                ];
            }

            $diasRestantes = $hoje->diffInDays($dataFim, false);
            $diasExpirado = $diasRestantes < 0 ? abs($diasRestantes) : 0;
            
            // Verificar se está suspensa
            if ($assinaturaDomain->status === 'suspensa') {
                return [
                    'pode_acessar' => false,
                    'code' => 'SUBSCRIPTION_SUSPENDED',
                    'message' => 'Sua assinatura está suspensa. Entre em contato com o suporte.',
                    'action' => 'contact_support',
                    'assinatura' => $assinaturaDomain,
                ];
            }

            // Verificar se está ativa (não expirada ou no grace period)
            $diasGracePeriod = 7; // Default, pode vir do plano
            $estaNoGracePeriod = $diasRestantes < 0 && abs($diasRestantes) <= $diasGracePeriod;
            $estaAtiva = $diasRestantes >= 0 || $estaNoGracePeriod;

            if ($estaAtiva) {
                // Ainda ativa ou no grace period
                $warning = $estaNoGracePeriod ? [
                    'warning' => true,
                    'dias_expirado' => $diasExpirado,
                ] : null;

                return [
                    'pode_acessar' => true,
                    'code' => 'SUBSCRIPTION_ACTIVE',
                    'warning' => $warning,
                    'assinatura' => $assinaturaDomain,
                ];
            }

            // Expirada fora do grace period
            return [
                'pode_acessar' => false,
                'code' => 'SUBSCRIPTION_EXPIRED',
                'message' => 'Sua assinatura expirou em ' . $dataFim->format('d/m/Y') . '. Renove sua assinatura para continuar usando o sistema.',
                'data_vencimento' => $dataFim->format('Y-m-d'),
                'dias_expirado' => $diasExpirado,
                'action' => 'renew',
                'assinatura' => $assinaturaDomain,
            ];

        } catch (NotFoundException $e) {
            return [
                'pode_acessar' => false,
                'code' => 'NO_SUBSCRIPTION',
                'message' => 'Nenhuma assinatura encontrada. Contrate um plano para continuar usando o sistema.',
                'action' => 'subscribe',
            ];
        } catch (\Exception $e) {
            \Log::error('Erro ao verificar assinatura ativa', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Em caso de erro, bloquear acesso por segurança
            return [
                'pode_acessar' => false,
                'code' => 'SUBSCRIPTION_CHECK_ERROR',
                'message' => 'Erro ao verificar assinatura. Entre em contato com o suporte.',
                'action' => 'contact_support',
            ];
        }
    }
}

