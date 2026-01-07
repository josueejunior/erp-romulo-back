<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Tenant\Repositories\TenantRepositoryInterface;
use App\Domain\Shared\ValueObjects\Senha;
use App\Domain\Exceptions\DomainException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Use Case para redefinir senha usando token
 * 
 * Responsabilidades:
 * - Validar token
 * - Buscar usuário em todos os tenants
 * - Atualizar senha
 * - Deletar token usado
 */
class RedefinirSenhaUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    /**
     * Executa o use case
     * 
     * @param string $email Email do usuário
     * @param string $token Token de reset
     * @param string $password Nova senha (plain text)
     * @throws DomainException Se token inválido ou usuário não encontrado
     */
    public function executar(string $email, string $token, string $password): void
    {
        // Verificar token no banco central
        $passwordReset = DB::connection()
            ->table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$passwordReset) {
            throw new DomainException('Token inválido ou expirado.', 400);
        }

        // Verificar se o token é válido
        if (!Hash::check($token, $passwordReset->token)) {
            throw new DomainException('Token inválido ou expirado.', 400);
        }

        // Verificar se o token expirou (60 minutos)
        $createdAt = Carbon::parse($passwordReset->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            throw new DomainException('Token expirado. Solicite um novo link de redefinição.', 400);
        }

        // Validar força da senha usando Value Object
        try {
            $senha = Senha::fromPlainText($password, validateStrength: true);
        } catch (DomainException $e) {
            throw new DomainException('Senha inválida: ' . $e->getMessage(), 422);
        }

        // Buscar usuário em todos os tenants
        $tenantsPaginator = $this->tenantRepository->buscarComFiltros(['per_page' => 10000]);
        $tenants = $tenantsPaginator->getCollection();
        $userUpdated = false;

        foreach ($tenants as $tenantDomain) {
            try {
                // Buscar modelo Eloquent para inicializar tenancy
                $tenant = $this->tenantRepository->buscarModeloPorId($tenantDomain->id);
                if (!$tenant) {
                    continue;
                }
                
                tenancy()->initialize($tenant);
                
                $userDomain = $this->userRepository->buscarPorEmail($email);
                
                if ($userDomain) {
                    // Buscar modelo Eloquent para atualizar
                    $userModel = \App\Modules\Auth\Models\User::where('email', $email)->first();
                    
                    if ($userModel) {
                        // Atualizar senha usando hash do Value Object
                        $userModel->password = $senha->hash;
                        $userModel->save();
                        $userUpdated = true;
                    }
                }
                
                tenancy()->end();
                
                if ($userUpdated) {
                    break;
                }
            } catch (\Exception $e) {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }
                Log::warning('Erro ao atualizar senha no tenant', [
                    'tenant_id' => $tenant->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$userUpdated) {
            throw new DomainException('Usuário não encontrado.', 404);
        }

        // Deletar token usado
        DB::connection()
            ->table('password_reset_tokens')
            ->where('email', $email)
            ->delete();
    }
}

