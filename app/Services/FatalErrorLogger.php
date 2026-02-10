<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Service para registrar apenas erros fatais em log separado
 * 
 * Captura:
 * - Fatal errors (E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR)
 * - Uncaught exceptions críticas
 * - Memory exhaustion
 * - Timeout fatal
 */
class FatalErrorLogger
{
    /**
     * Canal de log específico para fatal errors
     */
    private const FATAL_LOG_CHANNEL = 'fatal';

    /**
     * Registrar erro fatal
     * 
     * @param \Throwable|\Error $error
     * @param array $context Contexto adicional
     */
    public static function logFatal($error, array $context = []): void
    {
        try {
            $logData = [
                'type' => 'FATAL_ERROR',
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'code' => $error->getCode(),
                'class' => get_class($error),
                'trace' => $error->getTraceAsString(),
                'previous' => $error->getPrevious() ? [
                    'message' => $error->getPrevious()->getMessage(),
                    'file' => $error->getPrevious()->getFile(),
                    'line' => $error->getPrevious()->getLine(),
                    'class' => get_class($error->getPrevious()),
                ] : null,
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'memory_limit' => ini_get('memory_limit'),
                'timestamp' => now()->toIso8601String(),
                'context' => $context,
            ];

            // Registrar no canal específico de fatal errors
            Log::channel(self::FATAL_LOG_CHANNEL)->critical('FATAL ERROR', $logData);
            
            // Também registrar no log padrão para garantir
            Log::critical('FATAL ERROR (também registrado no log padrão)', $logData);
            
        } catch (\Throwable $e) {
            // Se falhar ao logar, tentar escrever diretamente no arquivo
            error_log(sprintf(
                "[%s] FATAL ERROR LOGGER FAILED: %s in %s:%d\nOriginal Error: %s",
                now()->toDateTimeString(),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $error->getMessage()
            ));
        }
    }

    /**
     * Verificar se é um erro fatal
     * 
     * @param \Throwable $error
     * @return bool
     */
    public static function isFatal($error): bool
    {
        // Erros fatais do PHP
        if ($error instanceof \Error) {
            return true;
        }

        // Exceções que indicam erros fatais
        $fatalExceptions = [
            \ParseError::class,
            \TypeError::class,
            \ErrorException::class,
        ];

        foreach ($fatalExceptions as $fatalClass) {
            if ($error instanceof $fatalClass) {
                return true;
            }
        }

        // Verificar mensagens que indicam erros fatais
        $fatalMessages = [
            'memory',
            'timeout',
            'fatal',
            'parse error',
            'syntax error',
            'maximum execution time',
            'allowed memory size',
        ];

        $message = strtolower($error->getMessage());
        foreach ($fatalMessages as $fatalMsg) {
            if (str_contains($message, $fatalMsg)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registrar erro fatal com contexto da requisição
     * 
     * @param \Throwable $error
     * @param \Illuminate\Http\Request|null $request
     */
    public static function logFatalWithRequest($error, $request = null): void
    {
        $context = [];
        
        if ($request) {
            $context = [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => auth()->id(),
                'tenant_id' => tenancy()->tenant?->id,
                'empresa_id' => $request->header('X-Empresa-ID'),
            ];
        }

        self::logFatal($error, $context);
    }
}

