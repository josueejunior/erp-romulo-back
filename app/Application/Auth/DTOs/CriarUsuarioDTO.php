<?php

namespace App\Application\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para criação de usuário
 * Transporta dados entre camadas sem expor entidades
 */
class CriarUsuarioDTO
{
    public function __construct(
        public readonly string $nome,
        public readonly string $email,
        public readonly string $senha,
        public readonly int $empresaId,
        public readonly string $role = 'Consulta',
        public readonly ?array $empresas = null, // Array de IDs de empresas (opcional)
    ) {}

    /**
     * Criar DTO a partir de Request
     * TenantContext é passado separadamente
     */
    public static function fromRequest(Request $request): self
    {
        // Normalizar role (trim e usar default se não fornecido)
        $role = $request->input('role');
        $role = $role ? trim($role) : 'Consulta';

        // Aceitar empresa_id OU empresa_ativa_id OU empresas (array)
        // Prioridade: empresa_id > empresa_ativa_id > primeira empresa do array empresas
        $empresaId = $request->input('empresa_id') ?? $request->input('empresa_ativa_id');
        $empresasArray = null;
        
        // Normalizar empresas: garantir que seja array de inteiros
        $empresas = $request->input('empresas');
        $empresasEnviadas = $request->has('empresas');
        
        if ($empresasEnviadas && is_array($empresas) && !empty($empresas)) {
            // Filtrar e validar IDs
            $empresasArray = array_filter(array_map('intval', $empresas), fn($id) => $id > 0);
            $empresasArray = array_values($empresasArray); // Reindexar
            
            // Se não tem empresa_id nem empresa_ativa_id, usar primeira do array
            if (!$empresaId && !empty($empresasArray)) {
                $empresaId = $empresasArray[0];
            }
            
            // Se empresa_ativa_id foi fornecido, garantir que está no array empresas
            if ($empresaId && !in_array((int)$empresaId, $empresasArray)) {
                // Se não está no array, usar a primeira empresa do array
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
            empresaId: $empresaId,
            role: $role,
            empresas: $empresasArray, // Array de IDs de empresas (pode ser null)
        );
    }
}

