<?php

namespace App\Application\Auth\UseCases;

use App\Application\Auth\DTOs\AtualizarUsuarioDTO;
use App\Domain\Auth\Entities\User;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Empresa\Repositories\EmpresaRepositoryInterface;
use App\Domain\Shared\ValueObjects\TenantContext;
use App\Domain\Shared\ValueObjects\Senha;
use App\Domain\Shared\ValueObjects\Email;
use App\Domain\Shared\Events\EventDispatcherInterface;
use App\Domain\Auth\Events\SenhaAlterada;
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

        // Validar email se foi alterado (usando Value Object Email)
        $email = null;
        if ($dto->email && $dto->email !== $userExistente->email) {
            // Value Object Email valida formato e normaliza automaticamente
            try {
                $email = Email::criar($dto->email);
            } catch (DomainException $e) {
                throw new DomainException('E-mail inválido: ' . $e->getMessage());
            }
            
            // Regra de negócio: verificar se email já existe
            if ($this->userRepository->emailExiste($email->value, $dto->userId)) {
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

        // Processar senha usando Value Object Senha (valida força automaticamente)
        $senhaHash = $userExistente->senhaHash;
        $senhaFoiAlterada = false;
        
        if ($dto->senha) {
            // Value Object Senha valida força e faz hash automaticamente
            $senha = Senha::fromPlainText($dto->senha, validateStrength: true);
            $senhaHash = $senha->hash;
            $senhaFoiAlterada = true;
        }

        // Criar nova instância com dados atualizados
        $userAtualizado = new User(
            id: $userExistente->id,
            tenantId: $userExistente->tenantId,
            nome: $dto->nome ?? $userExistente->nome,
            email: $email ? $email->value : ($dto->email ?? $userExistente->email),
            senhaHash: $senhaHash,
            empresaAtivaId: $dto->empresaId ?? $userExistente->empresaAtivaId,
        );

        // Atualizar (infraestrutura vai lidar com role se necessário)
        $userAtualizado = $this->userRepository->atualizar($userAtualizado);

        // Se role foi alterada, atualizar
        if ($dto->role) {
            $this->userRepository->atualizarRole($userAtualizado->id, $dto->role);
        }

        // Se empresas foram fornecidas (mesmo que array vazio), sincronizar
        // null = não altera, [] = remove todas, [1,2] = sincroniza com essas
        if ($dto->empresas !== null) {
            \Log::info('AtualizarUsuarioUseCase: Sincronizando empresas', [
                'user_id' => $userAtualizado->id,
                'empresas' => $dto->empresas,
                'empresas_count' => count($dto->empresas),
                'empresa_ativa_id' => $dto->empresaId,
            ]);
            
            // Validar que todas as empresas existem no tenant (se houver empresas)
            if (!empty($dto->empresas)) {
                foreach ($dto->empresas as $empresaId) {
                    $empresa = $this->empresaRepository->buscarPorId($empresaId);
                    if (!$empresa) {
                        throw new DomainException("Empresa ID {$empresaId} não encontrada neste tenant.");
                    }
                }
                
                // Garantir que empresa_ativa_id está nas empresas selecionadas
                $empresaAtivaFinal = $dto->empresaId;
                if ($empresaAtivaFinal && !in_array($empresaAtivaFinal, $dto->empresas)) {
                    // Se empresa_ativa_id não está nas empresas, usar a primeira
                    $empresaAtivaFinal = $dto->empresas[0];
                    \Log::info('AtualizarUsuarioUseCase: Empresa ativa ajustada', [
                        'empresa_ativa_original' => $dto->empresaId,
                        'empresa_ativa_final' => $empresaAtivaFinal,
                    ]);
                } elseif (!$empresaAtivaFinal && !empty($dto->empresas)) {
                    // Se não foi fornecida, usar a primeira empresa
                    $empresaAtivaFinal = $dto->empresas[0];
                    \Log::info('AtualizarUsuarioUseCase: Empresa ativa definida como primeira', [
                        'empresa_ativa_final' => $empresaAtivaFinal,
                    ]);
                }
                
                // Sincronizar empresas (IMPORTANTE: mesmo com 1 empresa, deve funcionar)
                $this->userRepository->sincronizarEmpresas($userAtualizado->id, $dto->empresas);
                \Log::info('AtualizarUsuarioUseCase: Empresas sincronizadas', [
                    'user_id' => $userAtualizado->id,
                    'empresas_sincronizadas' => $dto->empresas,
                ]);
                
                // Atualizar empresa_ativa_id se necessário
                if ($empresaAtivaFinal && $empresaAtivaFinal !== $userAtualizado->empresaAtivaId) {
                    $userAtualizado = new User(
                        id: $userAtualizado->id,
                        tenantId: $userAtualizado->tenantId,
                        nome: $userAtualizado->nome,
                        email: $userAtualizado->email,
                        senhaHash: $userAtualizado->senhaHash,
                        empresaAtivaId: $empresaAtivaFinal,
                    );
                    $userAtualizado = $this->userRepository->atualizar($userAtualizado);
                    \Log::info('AtualizarUsuarioUseCase: Empresa ativa atualizada', [
                        'user_id' => $userAtualizado->id,
                        'empresa_ativa_id' => $empresaAtivaFinal,
                    ]);
                }
            } else {
                // Array vazio: remover todas as empresas
                \Log::warning('AtualizarUsuarioUseCase: Removendo todas as empresas do usuário', [
                    'user_id' => $userAtualizado->id,
                ]);
                $this->userRepository->sincronizarEmpresas($userAtualizado->id, []);
                
                // Remover empresa_ativa_id
                if ($userAtualizado->empresaAtivaId !== null) {
                    $userAtualizado = new User(
                        id: $userAtualizado->id,
                        tenantId: $userAtualizado->tenantId,
                        nome: $userAtualizado->nome,
                        email: $userAtualizado->email,
                        senhaHash: $userAtualizado->senhaHash,
                        empresaAtivaId: null,
                    );
                    $userAtualizado = $this->userRepository->atualizar($userAtualizado);
                }
            }
        }

        // Disparar Domain Event se senha foi alterada
        if ($senhaFoiAlterada) {
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

