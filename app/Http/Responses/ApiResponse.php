<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * ResponseBuilder: Padroniza todas as respostas da API
 * Garante contrato consistente entre backend e frontend
 */
class ApiResponse
{
    /**
     * Retornar coleção (array) - SEMPRE array, mesmo com 1 item
     * Frontend pode usar .filter() sem quebrar
     */
    public static function collection(array $data, ?array $meta = null): JsonResponse
    {
        // Garantir que seja sempre array
        $data = is_array($data) ? $data : [];

        $response = ['data' => $data];

        if ($meta) {
            $response['meta'] = $meta;
        }

        return response()->json($response);
    }

    /**
     * Retornar item único como array (padronizado)
     * Frontend sempre recebe array, pode usar .filter() sem problemas
     */
    public static function item($item): JsonResponse
    {
        // Sempre retornar como array com 1 item
        $data = $item ? [$item] : [];

        return response()->json(['data' => $data]);
    }

    /**
     * Retornar item único como objeto (quando frontend espera objeto direto)
     * Use apenas quando frontend realmente espera objeto, não array
     */
    public static function single($item): JsonResponse
    {
        return response()->json(['data' => $item]);
    }

    /**
     * Retornar paginação (sempre retorna array)
     */
    public static function paginated(LengthAwarePaginator $paginator, ?callable $transformer = null): JsonResponse
    {
        $items = $paginator->getCollection();

        // Aplicar transformer se fornecido
        if ($transformer && is_callable($transformer)) {
            $items = $items->map($transformer);
        }

        // Converter Collection para array garantido
        $itemsArray = $items->values()->toArray();

        return response()->json([
            'data' => $itemsArray,
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ]);
    }

    /**
     * Retornar sucesso com mensagem
     */
    public static function success(string $message, $data = null, int $status = 200): JsonResponse
    {
        $response = [
            'message' => $message,
            'success' => true,
        ];

        if ($data !== null) {
            // Se for array, usar diretamente; se for objeto, converter para array
            $response['data'] = is_array($data) ? $data : (is_object($data) ? [$data] : $data);
        }

        return response()->json($response, $status);
    }

    /**
     * Retornar erro
     */
    public static function error(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        $response = [
            'message' => $message,
            'success' => false,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}

