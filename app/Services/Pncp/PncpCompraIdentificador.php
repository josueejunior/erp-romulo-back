<?php

declare(strict_types=1);

namespace App\Services\Pncp;

/**
 * Extrai CNPJ do órgão, ano e sequencial da compra a partir do texto do
 * "número de controle PNCP" (ex.: 18629840000183-1-000251/2025), de uma URL
 * que contenha esse código, do caminho REST da API
 * (.../orgaos/{cnpj}/compras/{ano}/{sequencial}) ou de query (?numeroControle=...).
 */
final class PncpCompraIdentificador
{
    /**
     * Número de controle com espaços opcionais (PDF, e-mail, cópia do portal).
     *
     * @return array{cnpj:string,ano:int,sequencial:int}|null
     */
    private static function matchNumeroControle(string $haystack): ?array
    {
        if (! preg_match('/\b(\d{14})\s*-\s*(\d+)\s*-\s*(\d+)\s*\/\s*(\d{4})\b/u', $haystack, $m)) {
            return null;
        }

        return [
            'cnpj' => $m[1],
            'sequencial' => (int) $m[3],
            'ano' => (int) $m[4],
        ];
    }

    /**
     * Caminho típico da API de consulta: /v1/orgaos/{cnpj}/compras/{ano}/{sequencial}.
     *
     * @return array{cnpj:string,ano:int,sequencial:int}|null
     */
    private static function matchPathCompras(string $haystack): ?array
    {
        if (! preg_match('#/orgaos/(\d{14})/compras/(\d{4})/(\d+)\b#', $haystack, $m)) {
            return null;
        }

        return [
            'cnpj' => $m[1],
            'ano' => (int) $m[2],
            'sequencial' => (int) $m[3],
        ];
    }

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

        $byControl = self::matchNumeroControle($decoded);
        if ($byControl !== null) {
            return $byControl;
        }

        $byPath = self::matchPathCompras($decoded);
        if ($byPath !== null) {
            return $byPath;
        }

        // Query string: numeroControle=... (portal / links compartilhados)
        if (preg_match('/(?:[?&#]|^)numeroControle=([^&#]+)/i', $decoded, $qm)) {
            $inner = rawurldecode(str_replace('+', ' ', trim($qm[1])));
            if ($inner !== '' && $inner !== $decoded) {
                $nested = self::matchNumeroControle($inner)
                    ?? self::matchPathCompras($inner);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        // Retrocompatível: padrão antigo sem word boundaries (texto colado “colado” no meio de parágrafos)
        if (preg_match('/(\d{14})-(\d+)-(\d+)\/(\d{4})/', $decoded, $m)) {
            return [
                'cnpj' => $m[1],
                'sequencial' => (int) $m[3],
                'ano' => (int) $m[4],
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $q  Query validada com referencia?, cnpj?, ano?, sequencial?
     * @return array{cnpj:string,ano:int,sequencial:int}|null
     */
    public static function fromQueryParams(array $q): ?array
    {
        $referencia = isset($q['referencia']) ? trim((string) $q['referencia']) : '';

        $ids = self::fromText($referencia);
        if ($ids === null && ! empty($q['cnpj']) && isset($q['ano'], $q['sequencial'])) {
            $cnpj = preg_replace('/\D/', '', (string) $q['cnpj']) ?? '';
            if (strlen($cnpj) === 14) {
                $ids = [
                    'cnpj' => $cnpj,
                    'ano' => (int) $q['ano'],
                    'sequencial' => (int) $q['sequencial'],
                ];
            }
        }

        return $ids;
    }
}
