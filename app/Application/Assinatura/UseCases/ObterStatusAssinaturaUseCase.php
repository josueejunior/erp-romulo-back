<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;
use Carbon\Carbon;

/**
 * Use Case: Obter Status da Assinatura com Limites Utilizados
 * Orquestra a busca do status da assinatura e cálculo de limites utilizados
 */
class ObterStatusAssinaturaUseCase
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
    ) {}

    /**
     * Executar o caso de uso
     * 
     * 🔥 CORRIGIDO: Assinatura pertence à empresa, não ao usuário
     * 
     * @param int $empresaId ID da empresa ativa (para buscar assinatura)
     * @param int $empresaIdParaContagem ID da empresa para contar usuários (geralmente o mesmo)
     * @return array Dados do status da assinatura
     * @throws NotFoundException Se a assinatura não for encontrada
     */
    public function executar(int $empresaId, int $empresaIdParaContagem): array
    {
        // 🔥 CORRIGIDO: Buscar assinatura pela empresa, não pelo usuário
        $assinatura = $this->assinaturaRepository->buscarAssinaturaAtualPorEmpresa($empresaId);

        if (!$assinatura) {
            throw new NotFoundException("Nenhuma assinatura encontrada para esta empresa.");
        }

        // Buscar modelo para acessar relacionamento com plano
        $assinaturaModel = $this->assinaturaRepository->buscarModeloPorId($assinatura->id);
        
        if (!$assinaturaModel || !$assinaturaModel->plano) {
            throw new NotFoundException("Plano da assinatura não encontrado.");
        }

        // Contar processos utilizados (no contexto do tenant)
        // Usar modelo diretamente apenas para contagem simples
        $processosUtilizados = \App\Modules\Processo\Models\Processo::count();

        // Contar usuários utilizados (no contexto da empresa)
        $usuariosUtilizados = \App\Modules\Auth\Models\User::whereHas('empresas', function($query) use ($empresaIdParaContagem) {
            $query->where('empresas.id', $empresaIdParaContagem);
        })->count();

        // ✅ NOVO: Calcular informações de grace period
        // Usar mesmo cálculo do VerificarAssinaturaAtivaPorEmpresaUseCase
        $hoje = Carbon::now()->startOfDay();
        $dataFim = $assinatura->dataFim?->copy()->startOfDay();
        
        if ($dataFim) {
            $diasRestantes = (int) $hoje->diffInDays($dataFim, false); // Pode ser negativo
            $diasExpirado = $diasRestantes < 0 ? abs($diasRestantes) : 0;
            $diasGracePeriod = $assinatura->diasGracePeriod ?? 7;
            $estaNoGracePeriod = $diasRestantes < 0 && abs($diasRestantes) <= $diasGracePeriod;
            
            $warning = $estaNoGracePeriod ? [
                'warning' => true,
                'dias_expirado' => $diasExpirado,
            ] : null;
        } else {
            $diasRestantes = 0;
            $diasExpirado = 0;
            $warning = null;
        }

        return [
            'status' => $assinatura->status,
            'limite_processos' => $assinaturaModel->plano->limite_processos,
            'limite_usuarios' => $assinaturaModel->plano->limite_usuarios,
            'limite_armazenamento_mb' => $assinaturaModel->plano->limite_armazenamento_mb,
            'processos_utilizados' => $processosUtilizados,
            'usuarios_utilizados' => $usuariosUtilizados,
            'restricao_diaria' => $assinaturaModel->plano->restricao_diaria ?? true,
            'recursos_disponiveis' => $assinaturaModel->plano->recursos_disponiveis ?? [],
            'mensagem' => $assinatura->isAtiva() ? 'Assinatura ativa' : 'Assinatura inativa',
            // ✅ NOVO: Informações de grace period
            'dias_restantes' => $diasRestantes,
            'dias_expirado' => $diasExpirado,
            'warning' => $warning,
        ];
    }
}

