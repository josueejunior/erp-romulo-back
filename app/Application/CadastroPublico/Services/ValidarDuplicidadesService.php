<?php

declare(strict_types=1);

namespace App\Application\CadastroPublico\Services;

use App\Domain\UsersLookup\Repositories\UserLookupRepositoryInterface;
use App\Domain\Exceptions\EmailJaCadastradoException;
use App\Domain\Exceptions\EmailEmpresaDesativadaException;
use App\Domain\Exceptions\CnpjJaCadastradoException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Service para validação de duplicidades usando tabela global de lookup
 * 
 * ⚡ Performance: O(1) ao invés de O(n) onde n = número de tenants
 */
final class ValidarDuplicidadesService
{
    public function __construct(
        private readonly UserLookupRepositoryInterface $lookupRepository,
    ) {}
    
    /**
     * Valida email em uma ÚNICA query no banco central
     * 
     * ⚡ Performance: O(1) - Uma única query ao invés de N queries (onde N = número de tenants)
     */
    public function validarEmail(string $email): void
    {
        Log::debug('ValidarDuplicidadesService: Validando email', [
            'email' => $email,
        ]);
        
        // Primeiro verificar se há registros ativos
        $lookupsAtivos = $this->lookupRepository->buscarAtivosPorEmail($email);
        
        if (!empty($lookupsAtivos)) {
            Log::warning('ValidarDuplicidadesService: Email já cadastrado', [
                'email' => $email,
                'registros_encontrados' => count($lookupsAtivos),
            ]);
            
            throw new EmailJaCadastradoException($email);
        }
        
        // Se não há registros ativos, verificar se há registros inativos (empresa desativada)
        $lookupsTodos = $this->lookupRepository->buscarTodosPorEmail($email);
        
        if (!empty($lookupsTodos)) {
            // Verificar se alguma empresa está desativada
            foreach ($lookupsTodos as $lookup) {
                if ($lookup->status === 'inativo' && $lookup->empresaId) {
                    // Verificar status da empresa no tenant
                    $empresaDesativada = $this->verificarEmpresaDesativada($lookup->tenantId, $lookup->empresaId);
                    
                    if ($empresaDesativada) {
                        Log::warning('ValidarDuplicidadesService: Email com empresa desativada', [
                            'email' => $email,
                            'tenant_id' => $lookup->tenantId,
                            'empresa_id' => $lookup->empresaId,
                        ]);
                        
                        throw new EmailEmpresaDesativadaException($email);
                    }
                }
            }
        }
        
        Log::debug('ValidarDuplicidadesService: Email validado com sucesso', [
            'email' => $email,
        ]);
    }
    
    /**
     * Verifica se a empresa está desativada no tenant
     */
    private function verificarEmpresaDesativada(int $tenantId, int $empresaId): bool
    {
        try {
            // Buscar tenant
            $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            
            if (!$tenant) {
                return false;
            }
            
            // Inicializar tenancy
            $tenantModel = \App\Models\Tenant::find($tenantId);
            if (!$tenantModel) {
                return false;
            }
            
            tenancy()->initialize($tenantModel);
            
            try {
                // Buscar empresa no tenant
                $empresa = DB::table('empresas')->where('id', $empresaId)->first();
                
                if (!$empresa) {
                    return false;
                }
                
                // Verificar se empresa está desativada
                $status = $empresa->status ?? 'inativa';
                $deletedAt = $empresa->deleted_at ?? null;
                
                return ($status === 'inativa' || $deletedAt !== null);
            } finally {
                tenancy()->end();
            }
        } catch (\Exception $e) {
            Log::error('ValidarDuplicidadesService: Erro ao verificar empresa desativada', [
                'tenant_id' => $tenantId,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    /**
     * Valida CNPJ em uma ÚNICA query no banco central
     * 
     * ⚡ Performance: O(1) - Uma única query ao invés de busca em múltiplos tenants
     */
    public function validarCnpj(string $cnpj): void
    {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj);
        
        Log::debug('ValidarDuplicidadesService: Validando CNPJ', [
            'cnpj' => $cnpj,
            'cnpj_limpo' => $cnpjLimpo,
        ]);
        
        $lookups = $this->lookupRepository->buscarAtivosPorCnpj($cnpjLimpo);
        
        if (!empty($lookups)) {
            Log::warning('ValidarDuplicidadesService: CNPJ já cadastrado', [
                'cnpj' => $cnpj,
                'cnpj_limpo' => $cnpjLimpo,
                'registros_encontrados' => count($lookups),
            ]);
            
            throw new CnpjJaCadastradoException($cnpj);
        }
        
        Log::debug('ValidarDuplicidadesService: CNPJ validado com sucesso', [
            'cnpj' => $cnpj,
            'cnpj_limpo' => $cnpjLimpo,
        ]);
    }
}




