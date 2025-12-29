<?php

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\AtualizarUsuarioDTO;
use App\Domain\Auth\Entities\User;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Domain\Shared\Events\EventDispatcherInterface;
use App\Domain\Auth\Events\SenhaAlterada;
use Illuminate\Support\Facades\Hash;
use DomainException;

/**
 * Use Case: Atualizar Usuário
 * Orquestra a atualização de usuário
 */
class AtualizarUsuarioUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EmpresaRepositoryInterface $empresaRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Executar o caso de uso
     * Recebe TenantContext explícito (não depende de request())
     */
    public function executar(AtualizarUsuarioDTO $dto, TenantContext $context): User
    {
        // Buscar usuário existente
        $userExistente = $this->userRepository->buscarPorId($dto->userId);
        if (!$userExistente) {
            throw new DomainException('Usuário não encontrado.');
        }

        // Validar se pertence ao tenant
        if ($userExistente->tenantId !== $context->tenantId) {
            throw new DomainException('Usuário não pertence ao tenant atual.');
        }

        // Validar email se foi alterado
        if ($dto->email && $dto->email !== $userExistente->email) {
            if ($this->userRepository->emailExiste($dto->email, $dto->userId)) {
                throw new DomainException('Este e-mail já está cadastrado.');
            }
        }

        // Validar empresa se foi alterada
        if ($dto->empresaId && $dto->empresaId !== $userExistente->empresaAtivaId) {
            $empresa = $this->empresaRepository->buscarPorId($dto->empresaId);
            if (!$empresa) {
                throw new DomainException('Empresa não encontrada neste tenant.');
            }
        }

        // Criar nova instância com dados atualizados
        $userAtualizado = new User(
            id: $userExistente->id,
            tenantId: $userExistente->tenantId,
            nome: $dto->nome ?? $userExistente->nome,
            email: $dto->email ?? $userExistente->email,
            senhaHash: $dto->senha ? Hash::make($dto->senha) : $userExistente->senhaHash,
            empresaAtivaId: $dto->empresaId ?? $userExistente->empresaAtivaId,
        );

        // Atualizar (infraestrutura vai lidar com role se necessário)
        $userAtualizado = $this->userRepository->atualizar($userAtualizado);

        // Se role foi alterada, atualizar
        if ($dto->role) {
            $this->userRepository->atualizarRole($userAtualizado->id, $dto->role);
        }

        // Se empresas foram fornecidas, sincronizar
        if ($dto->empresas !== null && !empty($dto->empresas)) {
            // Validar que todas as empresas existem no tenant
            foreach ($dto->empresas as $empresaId) {
                $empresa = $this->empresaRepository->buscarPorId($empresaId);
                if (!$empresa) {
                    throw new DomainException("Empresa ID {$empresaId} não encontrada neste tenant.");
                }
            }
            $this->userRepository->sincronizarEmpresas($userAtualizado->id, $dto->empresas);
        }

        // Disparar Domain Event se senha foi alterada
        if ($dto->senha) {
            $this->eventDispatcher->dispatch(
                new SenhaAlterada(
                    userId: $userAtualizado->id,
                    email: $userAtualizado->email,
                )
            );
        }

        return $userAtualizado;
    }
}

