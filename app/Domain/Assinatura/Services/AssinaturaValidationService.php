<?php

declare(strict_types=1);

namespace App\Domain\Assinatura\Services;

use App\Domain\Assinatura\Entities\Assinatura;
use App\Domain\Assinatura\Repositories\AssinaturaRepositoryInterface;
use App\Domain\Exceptions\DomainException;
use App\Domain\Plano\Repositories\PlanoRepositoryInterface;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Domain Service para validações complexas de assinatura
 * 
 * Responsabilidades:
 * - Validar integridade referencial (empresa, plano existem)
 * - Validar conflitos de assinatura (múltiplas ativas)
 * - Validar regras de negócio que requerem acesso a repositórios
 * 
 * 🔒 ROBUSTEZ: Centraliza validações complexas que não podem estar na entidade
 */
final class AssinaturaValidationService
{
    public function __construct(
        private readonly AssinaturaRepositoryInterface $assinaturaRepository,
        private readonly PlanoRepositoryInterface $planoRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * Valida se a empresa existe e está acessível
     * 
     * @throws DomainException Se empresa não existir ou não estiver acessível
     */
    public function validarEmpresaExiste(int $empresaId): void
    {
        $empresa = $this->empresaRepository->buscarPorId($empresaId);
        
        if (!$empresa) {
            Log::warning('AssinaturaValidationService - Empresa não encontrada', [
                'empresa_id' => $empresaId,
            ]);
            throw new DomainException("Empresa ID {$empresaId} não encontrada.");
        }
        
        Log::debug('AssinaturaValidationService - Empresa validada', [
            'empresa_id' => $empresaId,
        ]);
    }

    /**
     * Valida se o plano existe e está ativo
     * 
     * @throws DomainException Se plano não existir ou não estiver ativo
     */
    public function validarPlanoExisteEAtivo(int $planoId): void
    {
        $plano = $this->planoRepository->buscarPorId($planoId);
        
        if (!$plano) {
            Log::warning('AssinaturaValidationService - Plano não encontrado', [
                'plano_id' => $planoId,
            ]);
            throw new DomainException("Plano ID {$planoId} não encontrado.");
        }
        
        // Verificar se plano está ativo (se tiver propriedade ativo)
        if (property_exists($plano, 'ativo') && !$plano->ativo) {
            Log::warning('AssinaturaValidationService - Plano inativo', [
                'plano_id' => $planoId,
            ]);
            throw new DomainException("Plano ID {$planoId} está inativo e não pode ser usado para novas assinaturas.");
        }
        
        Log::debug('AssinaturaValidationService - Plano validado', [
            'plano_id' => $planoId,
        ]);
    }

    /**
     * Valida se não há conflito de assinaturas ativas para a mesma empresa
     * 
     * Regra: Uma empresa não pode ter múltiplas assinaturas ativas simultaneamente
     * 
     * @param int $empresaId ID da empresa
     * @param ?int $excluirAssinaturaId ID da assinatura a excluir da verificação (para atualizações)
     * @throws DomainException Se houver conflito
     */
    public function validarSemConflitoAssinaturaAtiva(int $empresaId, ?int $excluirAssinaturaId = null): void
    {
        try {
            $assinaturaAtual = $this->assinaturaRepository->buscarAssinaturaAtualPorEmpresa($empresaId);
            
            // Se não há assinatura atual, não há conflito
            if (!$assinaturaAtual) {
                Log::debug('AssinaturaValidationService - Nenhuma assinatura ativa encontrada', [
                    'empresa_id' => $empresaId,
                ]);
                return;
            }
            
            // Se estamos atualizando a mesma assinatura, não há conflito
            if ($excluirAssinaturaId && $assinaturaAtual->id === $excluirAssinaturaId) {
                Log::debug('AssinaturaValidationService - Atualizando assinatura existente, sem conflito', [
                    'empresa_id' => $empresaId,
                    'assinatura_id' => $excluirAssinaturaId,
                ]);
                return;
            }
            
            // Verificar se a assinatura atual está realmente ativa (não expirada)
            $hoje = Carbon::now()->startOfDay();
            $dataFim = $assinaturaAtual->dataFim?->startOfDay();
            
            if ($dataFim && $hoje->isAfter($dataFim)) {
                // Assinatura expirada, não há conflito
                $diasGracePeriod = $assinaturaAtual->diasGracePeriod ?? 7;
                $dataFimComGrace = $dataFim->copy()->addDays($diasGracePeriod);
                
                if ($hoje->isAfter($dataFimComGrace)) {
                    // Fora do grace period também, não há conflito
                    Log::debug('AssinaturaValidationService - Assinatura atual expirada, sem conflito', [
                        'empresa_id' => $empresaId,
                        'assinatura_atual_id' => $assinaturaAtual->id,
                        'data_fim' => $dataFim->toDateString(),
                    ]);
                    return;
                }
            }
            
            // Há uma assinatura ativa válida
            Log::warning('AssinaturaValidationService - Conflito de assinatura ativa detectado', [
                'empresa_id' => $empresaId,
                'assinatura_atual_id' => $assinaturaAtual->id,
                'status' => $assinaturaAtual->status,
                'data_fim' => $assinaturaAtual->dataFim?->toDateString(),
            ]);
            
            throw new DomainException(
                "Já existe uma assinatura ativa para esta empresa. " .
                "Cancele ou aguarde a expiração da assinatura atual antes de criar uma nova."
            );
            
        } catch (DomainException $e) {
            // Re-lançar DomainException
            throw $e;
        } catch (\Exception $e) {
            Log::error('AssinaturaValidationService - Erro ao validar conflito de assinatura', [
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Em caso de erro inesperado, não bloquear (fail-open para não quebrar o sistema)
            // Mas logar o erro para investigação
            Log::warning('AssinaturaValidationService - Validação de conflito falhou, permitindo criação', [
                'empresa_id' => $empresaId,
            ]);
        }
    }

    /**
     * Valida todas as regras antes de criar uma nova assinatura
     * 
     * @param int $empresaId ID da empresa
     * @param int $planoId ID do plano
     * @throws DomainException Se alguma validação falhar
     */
    public function validarAntesDeCriar(int $empresaId, int $planoId): void
    {
        Log::info('AssinaturaValidationService - Iniciando validações antes de criar assinatura', [
            'empresa_id' => $empresaId,
            'plano_id' => $planoId,
        ]);
        
        // 1. Validar empresa existe
        $this->validarEmpresaExiste($empresaId);
        
        // 2. Validar plano existe e está ativo
        $this->validarPlanoExisteEAtivo($planoId);
        
        // 3. Validar sem conflito de assinatura ativa
        $this->validarSemConflitoAssinaturaAtiva($empresaId);
        
        Log::info('AssinaturaValidationService - Todas as validações passaram', [
            'empresa_id' => $empresaId,
            'plano_id' => $planoId,
        ]);
    }

    /**
     * Valida todas as regras antes de atualizar uma assinatura
     * 
     * @param Assinatura $assinatura Assinatura a ser atualizada
     * @throws DomainException Se alguma validação falhar
     */
    public function validarAntesDeAtualizar(Assinatura $assinatura): void
    {
        if (!$assinatura->id) {
            throw new DomainException('Não é possível atualizar uma assinatura sem ID.');
        }
        
        Log::info('AssinaturaValidationService - Iniciando validações antes de atualizar assinatura', [
            'assinatura_id' => $assinatura->id,
            'empresa_id' => $assinatura->empresaId,
            'plano_id' => $assinatura->planoId,
        ]);
        
        // 1. Validar empresa existe (se empresaId mudou)
        if ($assinatura->empresaId) {
            $this->validarEmpresaExiste($assinatura->empresaId);
        }
        
        // 2. Validar plano existe e está ativo (se planoId mudou)
        $this->validarPlanoExisteEAtivo($assinatura->planoId);
        
        // 3. Validar sem conflito de assinatura ativa (excluindo a atual)
        if ($assinatura->empresaId) {
            $this->validarSemConflitoAssinaturaAtiva($assinatura->empresaId, $assinatura->id);
        }
        
        Log::info('AssinaturaValidationService - Todas as validações de atualização passaram', [
            'assinatura_id' => $assinatura->id,
        ]);
    }
}

