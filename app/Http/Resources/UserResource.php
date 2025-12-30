<?php

namespace App\Http\Resources;

use App\Domain\Auth\Entities\User;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para transformar Domain Entity User em JSON
 * Converte entidade de domínio para formato de resposta da API
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Se for uma entidade de domínio, buscar modelo Eloquent para incluir relacionamentos
        if ($this->resource instanceof User) {
            $userRepository = app(UserRepositoryInterface::class);
            $userModel = $userRepository->buscarModeloPorId($this->resource->id);
            
            if (!$userModel) {
                // Fallback: retornar apenas dados da entidade
                return [
                    'id' => $this->resource->id,
                    'name' => $this->resource->nome,
                    'email' => $this->resource->email,
                    'empresa_ativa_id' => $this->resource->empresaAtivaId,
                    'foto_perfil' => null, // Não disponível na entidade de domínio
                ];
            }
            
            // Usar modelo Eloquent para incluir relacionamentos
            return [
                'id' => $userModel->id,
                'name' => $userModel->name,
                'email' => $userModel->email,
                'empresa_ativa_id' => $userModel->empresa_ativa_id,
                'foto_perfil' => $userModel->foto_perfil,
                'role' => $userModel->roles->first()?->name ?? null,
                'empresas' => $userModel->empresas->pluck('id')->toArray(),
                'created_at' => $userModel->created_at?->toISOString(),
                'updated_at' => $userModel->updated_at?->toISOString(),
            ];
        }
        
        // Se já for um modelo Eloquent, usar diretamente
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'empresa_ativa_id' => $this->resource->empresa_ativa_id,
            'foto_perfil' => $this->resource->foto_perfil,
            'role' => $this->resource->roles->first()?->name ?? null,
            'empresas' => $this->resource->empresas->pluck('id')->toArray(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}

