<?php

namespace App\Helpers;

/**
 * Helper para sanitização de inputs
 * 
 * Remove HTML, scripts e caracteres potencialmente perigosos
 */
class InputSanitizer
{
    /**
     * Sanitiza uma string removendo HTML e scripts
     */
    public static function sanitizeString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Remover tags HTML e scripts
        $sanitized = strip_tags($value);
        
        // Escapar caracteres especiais
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        
        // Remover caracteres de controle
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/u', '', $sanitized);
        
        // Limitar tamanho máximo (prevenir DoS)
        if (strlen($sanitized) > 10000) {
            $sanitized = substr($sanitized, 0, 10000);
        }

        return trim($sanitized);
    }

    /**
     * Sanitiza um array recursivamente
     */
    public static function sanitizeArray(array $data, array $excludedKeys = []): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Pular campos excluídos (ex: senhas que serão hasheadas)
            if (in_array(strtolower($key), array_map('strtolower', $excludedKeys))) {
                $sanitized[$key] = $value;
                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = self::sanitizeString($value);
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value, $excludedKeys);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitiza email (validação adicional)
     */
    public static function sanitizeEmail(?string $email): ?string
    {
        if (empty($email)) {
            return null;
        }

        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return strtolower(trim($email));
    }

    /**
     * Sanitiza URL
     */
    public static function sanitizeUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $url;
    }

    /**
     * Sanitiza CNPJ (remove caracteres não numéricos)
     */
    public static function sanitizeCnpj(?string $cnpj): ?string
    {
        if (empty($cnpj)) {
            return null;
        }

        // Remover caracteres não numéricos
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);

        // Validar tamanho
        if (strlen($cnpj) !== 14) {
            return null;
        }

        return $cnpj;
    }

    /**
     * Sanitiza CPF (remove caracteres não numéricos)
     */
    public static function sanitizeCpf(?string $cpf): ?string
    {
        if (empty($cpf)) {
            return null;
        }

        // Remover caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // Validar tamanho
        if (strlen($cpf) !== 11) {
            return null;
        }

        return $cpf;
    }
}

