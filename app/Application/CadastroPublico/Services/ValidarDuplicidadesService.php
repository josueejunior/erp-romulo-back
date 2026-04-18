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
     * 
     * 🔥 CORREÇÃO: Verifica primeiro se empresas estão desativadas antes de lançar EmailJaCadastradoException
     */
    public function validarEmail(string $email): void
    {
        Log::debug('ValidarDuplicidadesService: Validando email', [
            'email' => $email,
        ]);
        
        // Buscar TODOS os registros (ativos e inativos) para verificar status das empresas
        $lookupsTodos = $this->lookupRepository->buscarTodosPorEmail($email);
        
        if (empty($lookupsTodos)) {
            Log::debug('ValidarDuplicidadesService: Email validado com sucesso (não encontrado)', [
                'email' => $email,
            ]);
            return; // Email não cadastrado, pode prosseguir
        }
        
        Log::debug('ValidarDuplicidadesService: Registros encontrados para email', [
            'email' => $email,
            'total_registros' => count($lookupsTodos),
        ]);
        
        // 🔥 CORREÇÃO: Verificar primeiro se há empresas desativadas
        // Se TODAS as empresas estão desativadas, lançar EmailEmpresaDesativadaException
        $temEmpresaAtiva = false;
        $temEmpresaDesativada = false;
        
        foreach ($lookupsTodos as $lookup) {
            if ($lookup->empresaId) {
                // Verificar status da empresa no tenant
                $empresaDesativada = $this->verificarEmpresaDesativada($lookup->tenantId, $lookup->empresaId);
                
                if ($empresaDesativada) {
                    $temEmpresaDesativada = true;
                    Log::debug('ValidarDuplicidadesService: Empresa desativada encontrada', [
                        'email' => $email,
                        'tenant_id' => $lookup->tenantId,
                        'empresa_id' => $lookup->empresaId,
                        'lookup_status' => $lookup->status,
                    ]);
                } else {
                    // Empresa está ativa
                    $temEmpresaAtiva = true;
                    Log::debug('ValidarDuplicidadesService: Empresa ativa encontrada', [
                        'email' => $email,
                        'tenant_id' => $lookup->tenantId,
                        'empresa_id' => $lookup->empresaId,
                        'lookup_status' => $lookup->status,
                    ]);
                }
            } else {
                // Se não tem empresaId, considerar como ativo (lookup ativo sem empresa específica)
                if ($lookup->status === 'ativo') {
                    $temEmpresaAtiva = true;
                    Log::debug('ValidarDuplicidadesService: Lookup ativo sem empresa específica', [
                        'email' => $email,
                        'tenant_id' => $lookup->tenantId,
                        'user_id' => $lookup->userId,
                    ]);
                }
            }
        }
        
        // 🔥 LÓGICA CORRIGIDA: Priorizar verificação de empresa desativada
        if ($temEmpresaDesativada && !$temEmpresaAtiva) {
            // TODAS as empresas estão desativadas
            Log::warning('ValidarDuplicidadesService: Email com empresa desativada (todas desativadas)', [
                'email' => $email,
                'total_registros' => count($lookupsTodos),
            ]);
            
            throw new EmailEmpresaDesativadaException($email);
        }
        
        if ($temEmpresaAtiva) {
            // Há pelo menos uma empresa ATIVA
            Log::warning('ValidarDuplicidadesService: Email já cadastrado (empresa ativa encontrada)', [
                'email' => $email,
                'registros_encontrados' => count($lookupsTodos),
            ]);
            
            throw new EmailJaCadastradoException($email);
        }
        
        // Se chegou aqui, todos os registros são inativos mas não têm empresa ou empresa não está desativada
        // Isso é um caso edge - permitir cadastro
        Log::debug('ValidarDuplicidadesService: Email validado com sucesso (apenas registros inativos sem empresa desativada)', [
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




