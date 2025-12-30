<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Use Case: Cancelar Assinatura
 */
class CancelarAssinaturaUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
    ) {}

    /**
     * Executa o caso de uso
     * 
     * @param int $tenantId ID do tenant
     * @param int $assinaturaId ID da assinatura
     * @return \App\Modules\Assinatura\Models\Assinatura Modelo da assinatura cancelada
     */
    public function executar(int $tenantId, int $assinaturaId): \App\Modules\Assinatura\Models\Assinatura
    {
        // Buscar assinatura usando repository DDD
        $assinaturaDomain = $this->assinaturaRepository->buscarPorId($assinaturaId);

        if (!$assinaturaDomain) {
            throw new NotFoundException('Assinatura não encontrada.');
        }

        // Validar que a assinatura pertence ao tenant
        if ($assinaturaDomain->tenantId !== $tenantId) {
            throw new NotFoundException('Assinatura não encontrada.');
        }

        // Validar que a assinatura pode ser cancelada
        if ($assinaturaDomain->status === 'cancelada') {
            throw new DomainException('Esta assinatura já está cancelada.');
        }

        // Buscar modelo para atualização
        $assinaturaModel = $this->assinaturaRepository->buscarModeloPorId($assinaturaId);

        if (!$assinaturaModel) {
            throw new NotFoundException('Assinatura não encontrada.');
        }

        // Atualizar status para cancelada
        $assinaturaModel->update([
            'status' => 'cancelada',
            'data_cancelamento' => Carbon::now(),
            'observacoes' => ($assinaturaModel->observacoes ?? '') . "\nCancelada em " . Carbon::now()->format('d/m/Y H:i:s'),
        ]);

        // Se era a assinatura atual do tenant, limpar referência
        $tenant = \App\Models\Tenant::find($tenantId);
        if ($tenant && $tenant->assinatura_atual_id === $assinaturaId) {
            $tenant->update([
                'assinatura_atual_id' => null,
                'plano_atual_id' => null,
            ]);

            Log::info('Assinatura atual cancelada - referências do tenant limpas', [
                'tenant_id' => $tenantId,
                'assinatura_id' => $assinaturaId,
            ]);
        }

        Log::info('Assinatura cancelada', [
            'tenant_id' => $tenantId,
            'assinatura_id' => $assinaturaId,
        ]);

        return $assinaturaModel->fresh();
    }
}

