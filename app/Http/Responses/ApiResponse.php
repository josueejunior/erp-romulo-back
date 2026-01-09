<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 🔥 DDD: ResponseBuilder padronizado
 * Garante contrato consistente entre backend e frontend
 * 
 * Padrão:
 * - success: { message, success: true, data? }
 * - error: { message, success: false, error?, errors? }
 * - paginated: { data: [], current_page, per_page, total, ... }
 */
class ApiResponse
{
    /**
     * Retornar coleção (array) - SEMPRE array, mesmo com 1 item
     * Frontend pode usar .filter() sem quebrar
     */
    public static function collection(array $data, ?array $meta = null): JsonResponse
    {
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
        $items = $paginator->items();
        
        if ($items instanceof \Illuminate\Support\Collection) {
            $items = $items->toArray();
        }
        
        $itemsArray = is_array($items) ? $items : [];

        if ($transformer && is_callable($transformer)) {
            $itemsArray = array_map($transformer, $itemsArray);
        }

        $itemsArray = array_values($itemsArray);

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
     * 🔥 DDD: Retornar sucesso padronizado
     * 
     * @param string $message Mensagem de sucesso
     * @param mixed $data Dados a retornar (opcional)
     * @param int $status Código HTTP (padrão: 200)
     * @return JsonResponse
     */
    public static function success(string $message, $data = null, int $status = 200): JsonResponse
    {
        $response = [
            'message' => $message,
            'success' => true,
        ];

        if ($data !== null) {
            $response['data'] = is_array($data) ? $data : (is_object($data) ? [$data] : $data);
        }

        return response()->json($response, $status);
    }

    /**
     * 🔥 DDD: Retornar erro padronizado
     * 
     * @param string $message Mensagem de erro para o usuário
     * @param int $status Código HTTP (padrão: 400)
     * @param string|null $error Detalhes técnicos do erro (apenas em debug)
     * @param array $errors Array de erros de validação (opcional)
     * @return JsonResponse
     */
    public static function error(string $message, int $status = 400, ?string $error = null, array $errors = []): JsonResponse
    {
        $response = [
            'message' => $message,
            'success' => false,
        ];

        if ($error && config('app.debug')) {
            $response['error'] = $error;
        }

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}
