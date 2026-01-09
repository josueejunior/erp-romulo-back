<?php

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\CriarUsuarioDTO;
use App\Domain\Auth\Entities\User;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Auth\Services\UserRoleServiceInterface;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Domain\Shared\Events\EventDispatcherInterface;
use App\Domain\Shared\ValueObjects\Email;
use App\Domain\Shared\ValueObjects\Senha;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Domain\Auth\Events\UsuarioCriado;
use App\Domain\Exceptions\DomainException;

/**
 * Use Case: Criar Usuário
 * Orquestra a criação de usuário, mas não sabe nada de banco de dados
 * Usa Value Objects e dispara Domain Events
 */
class CriarUsuarioUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EmpresaRepositoryInterface $empresaRepository,
        private UserRoleServiceInterface $roleService,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Executar o caso de uso
     * Recebe TenantContext explícito (não depende de request())
     */
    public function executar(CriarUsuarioDTO $dto, TenantContext $context): User
    {
        \Log::info('CriarUsuarioUseCase::executar iniciado', [
            'email' => $dto->email,
            'tenant_id' => $context->tenantId,
            'empresa_id' => $dto->empresaId,
        ]);

        // Validar email usando Value Object (factory method normaliza)
        $email = Email::criar($dto->email);
        
        // Validar se email já existe
        if ($this->userRepository->emailExiste($email->value)) {
            \Log::warning('CriarUsuarioUseCase - Email já existe', [
                'email' => $email->value,
            ]);
            throw new DomainException('Este e-mail já está cadastrado.');
        }

        // Verificar se empresa existe no tenant
        $empresa = $this->empresaRepository->buscarPorId($dto->empresaId);
        if (!$empresa) {
            \Log::warning('CriarUsuarioUseCase - Empresa não encontrada', [
                'empresa_id' => $dto->empresaId,
                'tenant_id' => $context->tenantId,
            ]);
            throw new DomainException('Empresa não encontrada neste tenant.');
        }

        try {
            // Criar senha usando Value Object (valida força e faz hash)
            $senha = Senha::fromPlainText($dto->senha);

            // Criar entidade User (regras de negócio)
            $user = new User(
                id: null, // Será gerado pelo repository
                tenantId: $context->tenantId,
                nome: $dto->nome,
                email: $email->value,
                senhaHash: $senha->hash,
                empresaAtivaId: $dto->empresaId,
            );

            // Persistir e associar empresa (infraestrutura)
            $user = $this->userRepository->criar($user, $dto->empresaId, $dto->role);

            \Log::info('CriarUsuarioUseCase - Usuário criado no repository', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            // Se múltiplas empresas foram fornecidas, sincronizar
            if ($dto->empresas !== null && !empty($dto->empresas)) {
                // Validar que todas as empresas existem no tenant
                foreach ($dto->empresas as $empresaId) {
                    $empresa = $this->empresaRepository->buscarPorId($empresaId);
                    if (!$empresa) {
                        throw new DomainException("Empresa ID {$empresaId} não encontrada neste tenant.");
                    }
                }
                // Sincronizar empresas
                $this->userRepository->sincronizarEmpresas($user->id, $dto->empresas);
            }

            // Atribuir role usando Domain Service
            $this->roleService->atribuirRole($user, $dto->role);

            \Log::info('CriarUsuarioUseCase - Role atribuída', [
                'user_id' => $user->id,
                'role' => $dto->role,
            ]);

            // Disparar Domain Event (desacoplado)
            $this->eventDispatcher->dispatch(
                new UsuarioCriado(
                    userId: $user->id,
                    email: $user->email,
                    nome: $user->nome,
                    tenantId: $user->tenantId,
                    empresaId: $user->empresaAtivaId,
                )
            );

            \Log::info('CriarUsuarioUseCase::executar concluído', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return $user;
        } catch (\Exception $e) {
            \Log::error('CriarUsuarioUseCase::executar falhou', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $dto->email,
            ]);
            throw $e;
        }
    }
}

