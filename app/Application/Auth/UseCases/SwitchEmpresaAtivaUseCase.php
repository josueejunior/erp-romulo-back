<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Auth\Entities\User;
use App\Domain\Auth\Events\EmpresaAtivaAlterada;
use App\Domain\Shared\Events\EventDispatcherInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Application\Assinatura\UseCases\VerificarAssinaturaAtivaPorEmpresaUseCase;
use DomainException;

/**
 * Use Case: Trocar Empresa Ativa
 * Orquestra a troca de empresa ativa e dispara Domain Event para limpar cache
 * 
 * 🔥 NOVO: Verifica se a empresa tem assinatura ativa após trocar
 */
class SwitchEmpresaAtivaUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EventDispatcherInterface $eventDispatcher,
        private VerificarAssinaturaAtivaPorEmpresaUseCase $verificarAssinaturaUseCase,
    ) {}

    /**
     * Executar o caso de uso
     * Retorna entidade de domínio atualizada
     */
    public function executar(int $userId, int $novaEmpresaId, TenantContext $context): User
    {
        // Buscar usuário
        $user = $this->userRepository->buscarPorId($userId);
        if (!$user) {
            throw new DomainException('Usuário não encontrado.');
        }

        // Validar se pertence ao tenant
        if ($user->tenantId !== $context->tenantId) {
            throw new DomainException('Usuário não pertence ao tenant atual.');
        }

        $empresaIdAntiga = $user->empresaAtivaId ?? 0;

        // Atualizar empresa ativa (delega para repository)
        $this->userRepository->atualizarEmpresaAtiva($userId, $novaEmpresaId);

        // Buscar usuário atualizado
        $userAtualizado = $this->userRepository->buscarPorId($userId);

        // Disparar Domain Event para limpar cache (infraestrutura vai lidar com isso)
        $this->eventDispatcher->dispatch(
            new EmpresaAtivaAlterada(
                userId: $userId,
                tenantId: $context->tenantId,
                empresaIdAntiga: $empresaIdAntiga,
                empresaIdNova: $novaEmpresaId,
                ocorreuEm: new \DateTimeImmutable(),
            )
        );

        // 🔥 NOVO: Verificar se a empresa tem assinatura ativa
        // Se não tiver, lançar exceção para que o controller retorne erro apropriado
        $resultadoAssinatura = $this->verificarAssinaturaUseCase->executar($novaEmpresaId);
        
        if (!$resultadoAssinatura['pode_acessar']) {
            // Armazenar resultado da verificação no usuário para o controller acessar
            // Por enquanto, lançamos uma exceção específica
            throw new \App\Domain\Exceptions\DomainException(
                $resultadoAssinatura['message'] ?? 'Esta empresa não possui uma assinatura ativa.',
                403, // Código HTTP (Forbidden)
                null, // Previous exception
                $resultadoAssinatura['code'] ?? 'NO_SUBSCRIPTION' // Código semântico
            );
        }

        return $userAtualizado;
    }
}



