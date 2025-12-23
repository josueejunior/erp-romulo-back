<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Traits\HasAuthContext;

/**
 * Controller base que combina RoutingController com HasAuthContext
 * Use este controller quando quiser usar a arquitetura padrão de services
 * 
 * Exemplo de uso:
 * 
 * class ProcessoController extends BaseServiceController
 * {
 *     use HasDefaultActions;
 *     
 *     protected ?string $storeDataCast = Processo::class;
 *     
 *     public function __construct(protected ProcessoService $service) {}
 * }
 */
abstract class BaseServiceController extends RoutingController
{
    use HasAuthContext;
    
    // Controller base já tem todos os handlers
    // Basta usar HasDefaultActions no controller filho
}

