<?php

namespace App\Application\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para registro de usuário
 * Reutiliza CriarUsuarioDTO mas adiciona tenant_id
 */
class RegisterDTO
{
    public function __construct(
        public readonly string $nome,
        public readonly string $email,
        public readonly string $senha,
        public readonly string $tenantId,
        public readonly int $empresaId,
        public readonly string $role = 'Consulta',
        public readonly ?array $empresas = null,
    ) {}

    /**
     * Criar DTO a partir de Request
     */
    public static function fromRequest(Request $request): self
    {
        // Normalizar role
        $role = $request->input('role') ? trim($request->input('role')) : 'Consulta';

        // Aceitar empresa_id OU empresa_ativa_id OU empresas (array)
        $empresaId = $request->input('empresa_id') ?? $request->input('empresa_ativa_id');
        $empresasArray = null;
        
        $empresas = $request->input('empresas');
        $empresasEnviadas = $request->has('empresas');
        
        if ($empresasEnviadas && is_array($empresas) && !empty($empresas)) {
            $empresasArray = array_filter(array_map('intval', $empresas), fn($id) => $id > 0);
            $empresasArray = array_values($empresasArray);
            
            if (!$empresaId && !empty($empresasArray)) {
                $empresaId = $empresasArray[0];
            }
        }
        
        if (!$empresaId) {
            throw new \InvalidArgumentException('É necessário fornecer empresa_id, empresa_ativa_id ou empresas.');
        }
        
        $empresaId = (int) $empresaId;

        return new self(
            nome: $request->input('name'),
            email: $request->input('email'),
            senha: $request->input('password'),
            tenantId: $request->input('tenant_id'),
            empresaId: $empresaId,
            role: $role,
            empresas: $empresasArray,
        );
    }
}





