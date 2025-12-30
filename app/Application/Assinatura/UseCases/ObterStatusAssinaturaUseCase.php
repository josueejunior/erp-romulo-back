<?php

namespace App\Application\Assinatura\UseCases;

use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Exceptions\NotFoundException;

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
     * @param int $tenantId ID do tenant
     * @param int $empresaId ID da empresa ativa (para contar usuários)
     * @return array Dados do status da assinatura
     * @throws NotFoundException Se a assinatura não for encontrada
     */
    public function executar(int $tenantId, int $empresaId): array
    {
        $assinatura = $this->assinaturaRepository->buscarAssinaturaAtual($tenantId);

        if (!$assinatura) {
            throw new NotFoundException("Nenhuma assinatura encontrada para este tenant.");
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
        $usuariosUtilizados = \App\Modules\Auth\Models\User::whereHas('empresas', function($query) use ($empresaId) {
            $query->where('empresas.id', $empresaId);
        })->count();

        return [
            'tenant_id' => $tenantId,
            'status' => $assinatura->status,
            'limite_processos' => $assinaturaModel->plano->limite_processos,
            'limite_usuarios' => $assinaturaModel->plano->limite_usuarios,
            'limite_armazenamento_mb' => $assinaturaModel->plano->limite_armazenamento_mb,
            'processos_utilizados' => $processosUtilizados,
            'usuarios_utilizados' => $usuariosUtilizados,
            'mensagem' => $assinatura->isAtiva() ? 'Assinatura ativa' : 'Assinatura inativa',
        ];
    }
}

