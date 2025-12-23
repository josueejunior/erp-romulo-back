<?php

namespace App\Rules;

use App\Database\Schema\Blueprint;

/**
 * Classe para gerar regras de validação baseadas em tipos de banco de dados
 * Mantém consistência entre migrations e validações
 * 
 * Exemplo de uso:
 * 'descricao' => ['required', ...DbTypeRule::string(DbTypeRule::VARCHAR_DEFAULT)]
 * 'email' => ['nullable', ...DbTypeRule::email()]
 * 'telefone' => ['nullable', ...DbTypeRule::telefone()]
 */
class DbTypeRule
{
    // Constantes de tamanho (reutilizando do Blueprint)
    public const int VARCHAR_TINY = Blueprint::VARCHAR_TINY;
    public const int VARCHAR_SMALL = Blueprint::VARCHAR_SMALL;
    public const int VARCHAR_DEFAULT = Blueprint::VARCHAR_DEFAULT;
    public const int VARCHAR_MEDIUM = Blueprint::VARCHAR_MEDIUM;
    public const int VARCHAR_LARGE = Blueprint::VARCHAR_LARGE;
    public const int VARCHAR_EXTRA_LARGE = Blueprint::VARCHAR_EXTRA_LARGE;

    /**
     * Gerar regras para campo string
     * 
     * @param int $maxLength Tamanho máximo (usar constantes VARCHAR_*)
     * @return array Regras de validação
     */
    public static function string(int $maxLength = self::VARCHAR_DEFAULT): array
    {
        return ['string', "max:{$maxLength}"];
    }

    /**
     * Gerar regras para campo texto (text)
     * 
     * @return array Regras de validação
     */
    public static function text(): array
    {
        return ['string'];
    }

    /**
     * Gerar regras para campo email
     * 
     * @return array Regras de validação
     */
    public static function email(): array
    {
        return ['email', "max:" . self::VARCHAR_DEFAULT];
    }

    /**
     * Gerar regras para campo telefone
     * 
     * @return array Regras de validação
     */
    public static function telefone(): array
    {
        return ['string', 'max:15'];
    }

    /**
     * Gerar regras para campo descrição
     * 
     * @return array Regras de validação
     */
    public static function descricao(): array
    {
        return self::string(self::VARCHAR_DEFAULT);
    }

    /**
     * Gerar regras para campo observação
     * 
     * @return array Regras de validação
     */
    public static function observacao(): array
    {
        return self::text();
    }

    /**
     * Gerar regras para campo decimal
     * 
     * @param int $precision Precisão total
     * @param int $scale Escala (casas decimais)
     * @return array Regras de validação
     */
    public static function decimal(int $precision = 15, int $scale = 2): array
    {
        return ['numeric', "max_digits:{$precision}", "decimal:{$scale}"];
    }

    /**
     * Gerar regras para campo integer
     * 
     * @return array Regras de validação
     */
    public static function integer(): array
    {
        return ['integer'];
    }

    /**
     * Gerar regras para campo boolean
     * 
     * @return array Regras de validação
     */
    public static function boolean(): array
    {
        return ['boolean'];
    }

    /**
     * Gerar regras para campo date
     * 
     * @return array Regras de validação
     */
    public static function date(): array
    {
        return ['date'];
    }

    /**
     * Gerar regras para campo datetime
     * 
     * @return array Regras de validação
     */
    public static function datetime(): array
    {
        return ['date'];
    }

    /**
     * Gerar regras para campo enum
     * 
     * @param array $values Valores permitidos
     * @return array Regras de validação
     */
    public static function enum(array $values): array
    {
        return ['in:' . implode(',', $values)];
    }

    /**
     * Gerar regras para campo URL
     * 
     * @param int $maxLength Tamanho máximo
     * @return array Regras de validação
     */
    public static function url(int $maxLength = 500): array
    {
        return ['url', "max:{$maxLength}"];
    }

    /**
     * Helper para required
     * 
     * @return string
     */
    public static function required(): string
    {
        return 'required';
    }

    /**
     * Helper para nullable
     * 
     * @return string
     */
    public static function nullable(): string
    {
        return 'nullable';
    }
}


