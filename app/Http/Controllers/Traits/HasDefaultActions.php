<?php

namespace App\Http\Controllers\Traits;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Trait que conecta métodos HTTP padrão aos handlers do controller base
 * Permite que controllers exponham rotas RESTful automaticamente
 */
trait HasDefaultActions
{
    /**
     * GET /resource/{id}
     * Buscar um registro específico
     */
    public function get(Request $request): JsonResponse
    {
        return $this->handleGet($request);
    }

    /**
     * GET /resource
     * Listar registros com paginação e filtros
     */
    public function list(Request $request): JsonResponse
    {
        return $this->handleList($request);
    }

    /**
     * POST /resource
     * Criar novo registro
     */
    public function store(Request $request): JsonResponse
    {
        return $this->handleStore($request);
    }

    /**
     * PUT/PATCH /resource/{id}
     * Atualizar registro existente
     */
    public function update(Request $request, int|string $id): JsonResponse
    {
        return $this->handleUpdate($request, $id);
    }

    /**
     * DELETE /resource/{id}
     * Excluir registro
     */
    public function destroy(Request $request, int|string $id): JsonResponse
    {
        return $this->handleDestroy($request, $id);
    }

    /**
     * DELETE /resource/bulk
     * Excluir múltiplos registros
     */
    public function destroyMany(Request $request): JsonResponse
    {
        return $this->handleDestroyMany($request);
    }
}


