<?php

declare(strict_types=1);

namespace App\Services\Pncp;

/**
 * Extrai CNPJ do órgão, ano e sequencial da compra a partir do texto do
 * "número de controle PNCP" (ex.: 18629840000183-1-000251/2025) ou de uma URL
 * que contenha esse padrão.
 */
final class PncpCompraIdentificador
{
    /**
     * @return array{cnpj:string,ano:int,sequencial:int}|null
     */
    public static function fromText(?string $text): ?array
    {
        if ($text === null) {
            return null;
        }
        $decoded = rawurldecode(trim($text));
        if ($decoded === '') {
            return null;
        }
        if (!preg_match('/(\d{14})-(\d+)-(\d+)\/(\d{4})/', $decoded, $m)) {
            return null;
        }

        return [
            'cnpj' => $m[1],
            'sequencial' => (int) $m[3],
            'ano' => (int) $m[4],
        ];
    }
}
