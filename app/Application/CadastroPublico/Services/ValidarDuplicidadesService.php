<?php

declare(strict_types=1);

namespace App\Application\CadastroPublico\Services;

use App\Domain\UsersLookup\Repositories\UserLookupRepositoryInterface;
use App\Domain\Exceptions\EmailJaCadastradoException;
use App\Domain\Exceptions\CnpjJaCadastradoException;
use Illuminate\Support\Facades\Log;

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
        
        $lookups = $this->lookupRepository->buscarAtivosPorEmail($email);
        
        if (!empty($lookups)) {
            Log::warning('ValidarDuplicidadesService: Email já cadastrado', [
                'email' => $email,
                'registros_encontrados' => count($lookups),
            ]);
            
            throw new EmailJaCadastradoException($email);
        }
        
        Log::debug('ValidarDuplicidadesService: Email validado com sucesso', [
            'email' => $email,
        ]);
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

