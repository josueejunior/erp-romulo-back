<?php

namespace App\Helpers;

/**
 * Helper para sanitizar dados sensíveis antes de logar
 */
class LogSanitizer
{
    /**
     * Campos sensíveis que devem ser mascarados
     */
    protected static array $sensitiveFields = [
        'password',
        'password_confirmation',
        'senha',
        'token',
        'api_key',
        'secret',
        'cpf',
        'cnpj',
        'email',
        'telefone',
        'telefones',
        'emails',
        'emails_adicionais',
        'banco',
        'agencia',
        'conta',
        'pix',
        'representante_legal_cpf',
    ];

    /**
     * Sanitiza um array de dados removendo ou mascarando campos sensíveis
     */
    public static function sanitize(array $data, bool $mask = true): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);

            // Verificar se é um campo sensível
            if (self::isSensitiveField($lowerKey)) {
                if ($mask) {
                    $sanitized[$key] = self::maskValue($value);
                } else {
                    $sanitized[$key] = '[REDACTED]';
                }
            } elseif (is_array($value)) {
                // Recursivamente sanitizar arrays
                $sanitized[$key] = self::sanitize($value, $mask);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Verifica se um campo é sensível
     */
    protected static function isSensitiveField(string $field): bool
    {
        foreach (self::$sensitiveFields as $sensitive) {
            if (str_contains($field, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mascara um valor sensível
     */
    protected static function maskValue($value): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '[REDACTED]';
        }

        $value = (string) $value;
        $length = strlen($value);

        if ($length <= 3) {
            return '***';
        }

        // Mostrar apenas primeiros 2 e últimos 2 caracteres
        $visibleStart = substr($value, 0, 2);
        $visibleEnd = substr($value, -2);
        $masked = str_repeat('*', max(4, $length - 4));

        return $visibleStart . $masked . $visibleEnd;
    }

    /**
     * Sanitiza uma mensagem de log removendo dados sensíveis
     */
    public static function sanitizeMessage(string $message): string
    {
        // Remover emails
        $message = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '[EMAIL]', $message);

        // Remover CPF (formato XXX.XXX.XXX-XX)
        $message = preg_replace('/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/', '[CPF]', $message);

        // Remover CNPJ (formato XX.XXX.XXX/XXXX-XX)
        $message = preg_replace('/\b\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}\b/', '[CNPJ]', $message);

        // Remover tokens longos
        $message = preg_replace('/\b[a-zA-Z0-9]{32,}\b/', '[TOKEN]', $message);

        return $message;
    }
}

