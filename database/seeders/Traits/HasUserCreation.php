<?php

namespace Database\Seeders\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Trait para seeders que precisam criar usuários
 * Fornece métodos auxiliares para criação de usuários com roles
 */
trait HasUserCreation
{
    /**
     * Cria ou atualiza um usuário com role
     */
    protected function createOrUpdateUser(array $userData, ?string $role = null)
    {
        $user = User::where('email', $userData['email'])->first();

        if (!$user) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password'] ?? 'password'),
            ]);

            $this->command->info("✅ Usuário criado: {$userData['email']}");
        } else {
            // Atualizar senha se fornecida
            if (isset($userData['password'])) {
                $user->password = Hash::make($userData['password']);
                $user->save();
            }
            
            $this->command->info("ℹ️  Usuário já existe: {$userData['email']}");
        }

        // Atribuir role se fornecido
        if ($role && method_exists($user, 'assignRole')) {
            try {
                $user->syncRoles([$role]);
                $this->command->info("   └─ Role atribuída: {$role}");
            } catch (\Exception $e) {
                $this->command->error("   └─ Erro ao atribuir role: " . $e->getMessage());
            }
        }

        return $user;
    }

    /**
     * Associa usuário a uma empresa
     */
    protected function associateUserToEmpresa(User $user, $empresa, string $perfil = 'consulta')
    {
        if (!$user->empresas->contains($empresa->id)) {
            $user->empresas()->attach($empresa->id, ['perfil' => $perfil]);
            $this->command->info("   └─ Usuário associado à empresa: {$empresa->razao_social} (perfil: {$perfil})");
        }

        // Definir empresa ativa se não tiver
        if (!$user->empresa_ativa_id) {
            $user->empresa_ativa_id = $empresa->id;
            $user->save();
            $this->command->info("   └─ Empresa ativa definida: {$empresa->razao_social}");
        }
    }
}

