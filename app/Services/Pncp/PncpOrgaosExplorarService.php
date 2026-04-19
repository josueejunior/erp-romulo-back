<?php

declare(strict_types=1);

namespace App\Services\Pncp;

/**
 * Deriva órgãos únicos a partir de {@see PncpConsultaService::contratacoesPublicacao},
 * já que o PNCP não expõe um endpoint de “listar todos os órgãos”.
 */
final class PncpOrgaosExplorarService
{
    public function __construct(
        private readonly PncpConsultaService $pncp,
    ) {}

    public static function fromConfig(): self
    {
        return new self(PncpConsultaService::fromConfig());
    }

    /**
     * @param  array{
     *   data_inicial:string,
     *   data_final:string,
     *   codigo_modalidade:int,
     *   pagina?:int,
     *   tamanho_pagina?:int,
     *   uf?:string|null,
     *   codigo_ibge?:string|null,
     *   cnpj?:string|null,
     *   texto?:string|null
     * }  $params
     * @return array{itens: array<int, array<string, mixed>>, raw: array<string, mixed>}
     */
    public function explorar(array $params): array
    {
        $raw = $this->pncp->contratacoesPublicacao($params);
        $rows = is_array($raw['data'] ?? null) ? $raw['data'] : [];

        $map = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $org = is_array($row['orgaoEntidade'] ?? null) ? $row['orgaoEntidade'] : [];
            $cnpj = preg_replace('/\D/', '', (string) ($org['cnpj'] ?? '')) ?? '';
            if (strlen($cnpj) !== 14) {
                continue;
            }
            if (isset($map[$cnpj])) {
                continue;
            }
            $uni = is_array($row['unidadeOrgao'] ?? null) ? $row['unidadeOrgao'] : null;
            $razao = trim((string) ($org['razaoSocial'] ?? $org['nome'] ?? ''));
            if ($razao === '') {
                $razao = 'Órgão (CNPJ '.$cnpj.')';
            }
            $cidade = is_array($uni) ? trim((string) ($uni['municipioNome'] ?? '')) : '';
            $uf = is_array($uni) ? strtoupper(substr((string) ($uni['ufSigla'] ?? ''), 0, 2)) : '';

            $map[$cnpj] = [
                'id' => 'pncp-'.$cnpj,
                'fonte' => 'pncp',
                'cnpj' => $cnpj,
                'razao_social' => $razao,
                'cidade' => $cidade !== '' ? $cidade : null,
                'estado' => strlen($uf) === 2 ? $uf : null,
                'ativo' => true,
                'cadastro_sugerido' => PncpCompraParaProcessoMapper::mapCadastroOrgaoSugerido($org, $uni),
            ];
        }

        $itens = array_values($map);
        $texto = isset($params['texto']) ? mb_strtolower(trim((string) $params['texto'])) : '';
        if ($texto !== '') {
            $itens = array_values(array_filter($itens, static function (array $o) use ($texto): bool {
                $blob = mb_strtolower(
                    ($o['razao_social'] ?? '').' '.($o['cnpj'] ?? '').' '.($o['cidade'] ?? '')
                );

                return str_contains($blob, $texto);
            }));
        }

        return [
            'itens' => $itens,
            'raw' => $raw,
        ];
    }
}
