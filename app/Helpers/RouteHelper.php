<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Route;

/**
 * Helper para registrar rotas de módulos automaticamente
 * Similar ao Route::module do exemplo fornecido
 */
class RouteHelper
{
    /**
     * Registrar rotas RESTful para um módulo
     * 
     * @param string $resource Nome do recurso (ex: 'processos')
     * @param string $controller Classe do controller
     * @param string $idParameter Nome do parâmetro de ID (padrão: 'id')
     * @param array $options Opções adicionais (middleware, prefix, etc)
     * 
     * @return void
     * 
     * Exemplo:
     * RouteHelper::module('processos', ProcessoController::class, 'processo_id');
     */
    public static function module(
        string $resource,
        string $controller,
        string $idParameter = 'id',
        array $options = []
    ): void {
        $middleware = $options['middleware'] ?? [];
        $prefix = $options['prefix'] ?? '';
        $namePrefix = $options['name'] ?? $resource;

        Route::prefix($prefix)->middleware($middleware)->group(function () use (
            $resource,
            $controller,
            $idParameter,
            $namePrefix
        ) {
            // GET /resource - Listar
            Route::get($resource, [$controller, 'list'])
                ->name("{$namePrefix}.list");

            // POST /resource - Criar
            Route::post($resource, [$controller, 'store'])
                ->name("{$namePrefix}.store");

            // GET /resource/{id} - Buscar
            Route::get("{$resource}/{{$idParameter}}", [$controller, 'get'])
                ->name("{$namePrefix}.get");

            // PUT/PATCH /resource/{id} - Atualizar
            Route::match(['put', 'patch'], "{$resource}/{{$idParameter}}", [$controller, 'update'])
                ->name("{$namePrefix}.update");

            // DELETE /resource/{id} - Excluir
            Route::delete("{$resource}/{{$idParameter}}", [$controller, 'destroy'])
                ->name("{$namePrefix}.destroy");

            // DELETE /resource/bulk - Excluir múltiplos
            Route::delete("{$resource}/bulk", [$controller, 'destroyMany'])
                ->name("{$namePrefix}.destroyMany");
        });
    }

    /**
     * Registrar rotas aninhadas (recurso dentro de outro)
     * 
     * @param string $parentResource Nome do recurso pai (ex: 'processos')
     * @param string $childResource Nome do recurso filho (ex: 'itens')
     * @param string $controller Classe do controller
     * @param string $parentIdParameter Nome do parâmetro do pai (padrão: 'processo_id')
     * @param string $childIdParameter Nome do parâmetro do filho (padrão: 'id')
     * @param array $options Opções adicionais
     * 
     * @return void
     * 
     * Exemplo:
     * RouteHelper::nested('processos', 'itens', ProcessoItemController::class);
     */
    public static function nested(
        string $parentResource,
        string $childResource,
        string $controller,
        string $parentIdParameter = null,
        string $childIdParameter = 'id',
        array $options = []
    ): void {
        $parentIdParameter = $parentIdParameter ?? "{$parentResource}_id";
        $middleware = $options['middleware'] ?? [];
        $prefix = $options['prefix'] ?? '';
        $namePrefix = $options['name'] ?? "{$parentResource}.{$childResource}";

        Route::prefix($prefix)->middleware($middleware)->group(function () use (
            $parentResource,
            $childResource,
            $controller,
            $parentIdParameter,
            $childIdParameter,
            $namePrefix
        ) {
            $path = "{$parentResource}/{{$parentIdParameter}}/{$childResource}";

            // GET /parent/{parent_id}/child - Listar
            Route::get($path, [$controller, 'list'])
                ->name("{$namePrefix}.list");

            // POST /parent/{parent_id}/child - Criar
            Route::post($path, [$controller, 'store'])
                ->name("{$namePrefix}.store");

            // GET /parent/{parent_id}/child/{id} - Buscar
            Route::get("{$path}/{{$childIdParameter}}", [$controller, 'get'])
                ->name("{$namePrefix}.get");

            // PUT/PATCH /parent/{parent_id}/child/{id} - Atualizar
            Route::match(['put', 'patch'], "{$path}/{{$childIdParameter}}", [$controller, 'update'])
                ->name("{$namePrefix}.update");

            // DELETE /parent/{parent_id}/child/{id} - Excluir
            Route::delete("{$path}/{{$childIdParameter}}", [$controller, 'destroy'])
                ->name("{$namePrefix}.destroy");
        });
    }
}







