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
     * 🔥 NOVO: Assinatura pertence ao usuário, não ao tenant
     * 
     * @param int $userId ID do usuário
     * @return array Array com informações sobre a assinatura e se pode acessar
     */
    public function executar(int $userId): array
    {
        try {
            \Log::info('VerificarAssinaturaAtivaUseCase - Iniciando verificação', [
                'user_id' => $userId,
                'tenancy_initialized' => tenancy()->initialized,
                'tenancy_tenant_id' => tenancy()->tenant?->id,
            ]);
            
            $assinaturaDomain = $this->assinaturaRepository->buscarAssinaturaAtualPorUsuario($userId);
            
            \Log::info('VerificarAssinaturaAtivaUseCase - Resultado da busca', [
                'user_id' => $userId,
                'assinatura_encontrada' => $assinaturaDomain !== null,
                'assinatura_id' => $assinaturaDomain?->id,
                'assinatura_status' => $assinaturaDomain?->status,
            ]);
            
            if (!$assinaturaDomain) {
                \Log::warning('VerificarAssinaturaAtivaUseCase - Assinatura não encontrada', [
                    'user_id' => $userId,
                ]);
                
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

            // Calcular dias restantes (apenas dias completos, sem horas)
            $hoje = $hoje->startOfDay();
            $dataFim = $dataFim->copy()->startOfDay();
            $diasRestantes = (int) $hoje->diffInDays($dataFim, false);
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
                'user_id' => $userId,
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

