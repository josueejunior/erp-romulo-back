<?php

namespace App\Application\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para atualização de usuário
 */
class AtualizarUsuarioDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly ?string $nome = null,
        public readonly ?string $email = null,
        public readonly ?string $senha = null,
        public readonly ?int $empresaId = null,
        public readonly ?array $empresas = null, // Array de IDs de empresas
        public readonly ?string $role = null,
    ) {}

    /**
     * Criar DTO a partir de Request
     * TenantContext é passado separadamente
     */
    public static function fromRequest(Request $request, int $userId): self
    {
        // Normalizar role (trim e garantir que está vazio se null)
        $role = $request->input('role');
        $role = $role ? trim($role) : null;

        // Normalizar senha: string vazia vira null
        $senha = $request->input('password');
        $senha = ($senha && trim($senha) !== '') ? trim($senha) : null;

        // Normalizar empresas: garantir que seja array de inteiros
        // IMPORTANTE: Se empresas for enviado (mesmo vazio), deve sincronizar
        // Se não for enviado (null), não altera as empresas existentes
        $empresas = $request->input('empresas');
        $empresasEnviadas = $request->has('empresas'); // Verifica se a chave existe
        
        if ($empresasEnviadas) {
            // Se foi enviado, normalizar (mesmo que seja array vazio)
            if (!is_array($empresas)) {
                $empresas = $empresas ? [$empresas] : [];
            }
            // Filtrar e validar IDs
            $empresas = array_filter(array_map('intval', $empresas), fn($id) => $id > 0);
            $empresas = array_values($empresas); // Reindexar array
            // Se ficou vazio após filtrar, manter como array vazio (será sincronizado)
        } else {
            // Se não foi enviado, manter null (não altera empresas)
            $empresas = null;
        }

        // empresa_id pode vir separado ou como empresa_ativa_id
        $empresaId = $request->input('empresa_id') ?? $request->input('empresa_ativa_id');

        return new self(
            userId: $userId,
            nome: $request->input('name'),
            email: $request->input('email'),
            senha: $senha, // Pode ser null no update
            empresaId: $empresaId ? (int) $empresaId : null,
            empresas: $empresas, // Array de IDs de empresas
            role: $role,
        );
    }
}

