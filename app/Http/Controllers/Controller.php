<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\RoutingController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use App\Models\Empresa;

/**
 * Controller base abstrato
 * Estende RoutingController para fornecer handlers padrão
 * 
 * Características:
 * - Propriedade $service para injeção do service
 * - $storeDataCast define o DTO usado no store
 * - $routeParentIdBinding para rotas aninhadas
 * - Métodos auxiliares para requisições e respostas
 */
abstract class Controller extends RoutingController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * @var null|array{parameter: string, inject: "argument"|"params"}
     */
    protected ?array $routeParentIdBinding = null;

    /**
     * Classe do modelo para casting de dados
     * Pode ser sobrescrito no controller filho
     */
    protected ?string $storeDataCast = null;

    /**
     * Obter o service
     * @throws \Exception Se service não estiver definido
     */
    protected function getService(): object
    {
        return $this->service ?? throw new \Exception(
            "Missing service mapping at [" . static::class . "]."
        );
    }

    /**
     * Obtém a empresa do usuário autenticado (compatibilidade)
     * @deprecated Use getEmpresa() do trait HasAuthContext
     */
    protected function getEmpresaAtiva(): ?Empresa
    {
        // Usar o método do trait se disponível
        if (method_exists($this, 'getEmpresa')) {
            return $this->getEmpresa();
        }

        // Fallback para código legado
        $user = auth()->user();
        if (!$user || !$user->empresa_ativa_id) {
            return null;
        }

        return Empresa::find($user->empresa_ativa_id);
    }

    /**
     * Obtém a empresa do usuário autenticado ou falha (compatibilidade)
     * @deprecated Use getEmpresaOrFail() do trait HasAuthContext
     */
    protected function getEmpresaAtivaOrFail(): Empresa
    {
        // Usar o método do trait se disponível
        if (method_exists($this, 'getEmpresaOrFail')) {
            return $this->getEmpresaOrFail();
        }

        // Fallback para código legado
        $empresa = $this->getEmpresaAtiva();
        
        if (!$empresa) {
            abort(403, 'Nenhuma empresa ativa selecionada.');
        }

        return $empresa;
    }
}
