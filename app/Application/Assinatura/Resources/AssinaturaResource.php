<?php

namespace App\Application\Assinatura\Resources;

use App\Domain\Assinatura\Entities\Assinatura;
use App\Application\Assinatura\DTOs\AssinaturaResponseDTO;
use App\Application\Assinatura\DTOs\PlanoResponseDTO;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;

/**
 * Resource para transformar entidade Assinatura em DTO de resposta
 * Segue padrão DDD: Controller → Use Case → Domain → Repository
 * Resource transforma Domain Entity → Response DTO
 */
class AssinaturaResource
{
    public function __construct(
        private AssinaturaRepositoryInterface $assinaturaRepository,
    ) {}

    /**
     * Transformar entidade Assinatura em DTO de resposta
     * 
     * @param Assinatura $assinatura Entidade do domínio
     * @return AssinaturaResponseDTO DTO para resposta JSON
     */
    public function toResponse(Assinatura $assinatura): AssinaturaResponseDTO
    {
        // Buscar modelo para acessar relacionamento com plano (necessário para resposta)
        $model = $this->assinaturaRepository->buscarModeloPorId($assinatura->id);
        
        $planoDTO = null;
        if ($model && $model->plano) {
            $planoDTO = new PlanoResponseDTO(
                id: $model->plano->id,
                nome: $model->plano->nome,
                descricao: $model->plano->descricao,
                precoMensal: $model->plano->preco_mensal,
                precoAnual: $model->plano->preco_anual,
                limiteProcessos: $model->plano->limite_processos,
                limiteUsuarios: $model->plano->limite_usuarios,
                limiteArmazenamentoMb: $model->plano->limite_armazenamento_mb,
            );
        }

        // Calcular dias restantes usando método do modelo (mantém compatibilidade)
        $diasRestantes = $model ? $model->diasRestantes() : $assinatura->diasRestantes();

        return new AssinaturaResponseDTO(
            id: $assinatura->id,
            planoId: $assinatura->planoId,
            status: $assinatura->status,
            dataInicio: $assinatura->dataInicio?->format('Y-m-d'),
            dataFim: $assinatura->dataFim?->format('Y-m-d'),
            valorPago: $assinatura->valorPago,
            metodoPagamento: $assinatura->metodoPagamento,
            transacaoId: $assinatura->transacaoId,
            diasRestantes: $diasRestantes,
            plano: $planoDTO,
        );
    }
}



