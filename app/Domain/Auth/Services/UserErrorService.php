<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Repositories\UserReadRepositoryInterface;
use App\Domain\Tenant\Entities\Tenant;
use App\Services\AdminTenancyRunner;

/**
 * 🔥 DDD: Domain Service para tratar erros de usuário
 * Encapsula lógica de buscar usuário existente e montar resposta de erro
 */
class UserErrorService
{
    public function __construct(
        private UserReadRepositoryInterface $userReadRepository,
        private AdminTenancyRunner $adminTenancyRunner,
    ) {}

    /**
     * Busca usuário existente e monta resposta de erro customizada
     * 
     * @param string $email Email do usuário
     * @param Tenant $tenant Tenant atual
     * @param string $message Mensagem de erro original
     * @return array|null Array com resposta customizada ou null se não encontrar
     */
    public function buscarUsuarioExistenteParaErro(string $email, Tenant $tenant, string $message): ?array
    {
        // Se erro não for sobre email duplicado, não buscar
        if (!str_contains($message, 'já está cadastrado') && !str_contains($message, 'já existe')) {
            return null;
        }

        if (!str_contains(strtolower($message), 'email') && !str_contains(strtolower($message), 'e-mail')) {
            return null;
        }

        try {
            $userExistente = $this->adminTenancyRunner->runForTenant($tenant, function () use ($email) {
                return $this->userReadRepository->buscarPorEmail($email);
            });

            if (!$userExistente) {
                return null;
            }

            // Montar resposta customizada com informações do usuário existente
            return [
                'message' => $message,
                'errors' => [
                    'email' => [
                        $message . ' Este usuário já existe no sistema. Use a opção "Vincular usuário existente" ou atualize o usuário existente para adicioná-lo a esta empresa.'
                    ]
                ],
                'existing_user' => [
                    'id' => $userExistente['id'],
                    'name' => $userExistente['name'],
                    'email' => $userExistente['email'],
                    'empresas' => $userExistente['empresas'] ?? [],
                    'can_link' => true,
                ],
                'suggestion' => 'use_existing_user_link',
            ];
        } catch (\Exception $e) {
            \Log::warning('UserErrorService: Erro ao buscar usuário existente', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Determina campo de erro baseado na mensagem
     */
    public function determinarCampoErro(string $message): string
    {
        if (str_contains($message, 'senha') || str_contains($message, 'Senha')) {
            return 'password';
        }
        
        if (str_contains($message, 'email') || str_contains($message, 'E-mail') || str_contains($message, 'e-mail')) {
            return 'email';
        }
        
        if (str_contains($message, 'empresa') || str_contains($message, 'Empresa')) {
            return 'empresa_id';
        }

        return 'general';
    }
}




