<?php

declare(strict_types=1);

namespace App\Services\Pncp;

/**
 * Converte respostas da API pública PNCP em sugestões para o formulário de processo.
 */
final class PncpCompraParaProcessoMapper
{
    public static function cnpjNumeros(?string $c): ?string
    {
        $d = preg_replace('/\D/', '', (string) $c) ?? '';

        return strlen($d) === 14 ? $d : null;
    }

    public static function orgaoCnpjFromCompra(array $compra): ?string
    {
        $org = is_array($compra['orgaoEntidade'] ?? null) ? $compra['orgaoEntidade'] : [];

        return self::cnpjNumeros($org['cnpj'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $compra
     * @return array<string, mixed>
     */
    public static function mapProcessoSugerido(array $compra): array
    {
        $modalNome = isset($compra['modalidadeNome']) ? mb_strtolower((string) $compra['modalidadeNome']) : '';
        $modalidade = str_contains($modalNome, 'dispensa') ? 'dispensa' : 'pregao';

        $objeto = isset($compra['objetoCompra']) ? trim((string) $compra['objetoCompra']) : '';
        $numero = self::firstNonEmptyString([
            $compra['numeroCompra'] ?? null,
            $compra['processo'] ?? null,
            $compra['numeroControlePNCP'] ?? null,
        ]) ?? '';

        $linkEdital = self::firstNonEmptyString([
            $compra['linkSistemaOrigem'] ?? null,
            $compra['linkProcessoEletronico'] ?? null,
        ]);

        $dt = self::mapDatetimeLocal($compra['dataAberturaProposta'] ?? null)
            ?? self::mapDatetimeLocal($compra['dataEncerramentoProposta'] ?? null);

        $srp = false;
        foreach (['ampRegistroPreco', 'registroPreco'] as $k) {
            if (isset($compra[$k]) && (bool) $compra[$k]) {
                $srp = true;
                break;
            }
        }
        if (! $srp && $modalNome !== '' && str_contains($modalNome, 'registro')) {
            $srp = true;
        }

        return [
            'modalidade' => $modalidade,
            'numero_modalidade' => $numero,
            'objeto_resumido' => $objeto !== '' ? $objeto : null,
            'link_edital' => $linkEdital,
            'portal' => 'PNCP',
            'numero_processo_administrativo' => isset($compra['processo']) ? trim((string) $compra['processo']) : null,
            'numero_edital' => isset($compra['numeroCompra']) ? trim((string) $compra['numeroCompra']) : null,
            'data_hora_sessao_publica' => $dt,
            'srp' => $srp,
        ];
    }

    /**
     * @param  array<int, mixed>  $itensRaw
     * @return array<int, array<string, mixed>>
     */
    public static function mapItensParaFormulario(array $itensRaw): array
    {
        $out = [];
        $idx = 0;
        foreach ($itensRaw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $idx++;
            $n = (int) ($row['numeroItem'] ?? $idx);
            $desc = isset($row['descricao']) ? trim((string) $row['descricao']) : '';
            $q = $row['quantidade'] ?? null;
            $quantidade = is_numeric($q) ? (string) $q : '';
            $vu = $row['valorUnitarioEstimado'] ?? null;
            $valorEstimado = $vu !== null && is_numeric($vu) ? (string) $vu : '';

            $out[] = [
                'id' => 'temp-pncp-'.$idx.'-'.bin2hex(random_bytes(3)),
                'numero_item' => $n > 0 ? $n : $idx,
                'quantidade' => $quantidade,
                'unidade' => isset($row['unidadeMedida']) ? trim((string) $row['unidadeMedida']) : '',
                'especificacao_tecnica' => $desc,
                'marca_modelo_referencia' => '',
                'exige_atestado' => false,
                'quantidade_atestado_cap_tecnica' => '',
                'valor_estimado' => $valorEstimado,
                'fornecedor_id' => '',
                'transportadora_id' => '',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $orgaoEntidade
     * @param  array<string, mixed>|null  $unidadeOrgao
     * @return array<string, mixed>
     */
    public static function mapCadastroOrgaoSugerido(array $orgaoEntidade, ?array $unidadeOrgao): array
    {
        $cnpj = self::cnpjNumeros($orgaoEntidade['cnpj'] ?? null);
        $razao = isset($orgaoEntidade['razaoSocial']) ? trim((string) $orgaoEntidade['razaoSocial']) : '';
        if ($razao === '' && isset($orgaoEntidade['nome'])) {
            $razao = trim((string) $orgaoEntidade['nome']);
        }
        $uasg = null;
        if (is_array($unidadeOrgao)) {
            $uasg = $unidadeOrgao['codigoUnidade'] ?? $unidadeOrgao['codigo'] ?? $unidadeOrgao['uasg'] ?? null;
            if ($uasg !== null && ! is_string($uasg)) {
                $uasg = (string) $uasg;
            }
        }

        $cidade = is_array($unidadeOrgao) ? trim((string) ($unidadeOrgao['municipioNome'] ?? '')) : '';
        $estado = is_array($unidadeOrgao) ? strtoupper(substr((string) ($unidadeOrgao['ufSigla'] ?? ''), 0, 2)) : '';

        return [
            'uasg' => $uasg !== null && $uasg !== '' ? $uasg : null,
            'razao_social' => $razao !== '' ? $razao : null,
            'cnpj' => $cnpj,
            'cep' => null,
            'logradouro' => null,
            'numero' => null,
            'bairro' => null,
            'complemento' => null,
            'cidade' => $cidade !== '' ? $cidade : null,
            'estado' => strlen($estado) === 2 ? $estado : null,
            'email' => null,
            'telefone' => null,
            'observacoes' => 'Cadastro sugerido a partir do PNCP.',
        ];
    }

    private static function mapDatetimeLocal(mixed $iso): ?string
    {
        if (! is_string($iso) || trim($iso) === '') {
            return null;
        }
        try {
            $c = new \DateTimeImmutable($iso);

            return $c->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int|string, mixed>  $candidates
     */
    private static function firstNonEmptyString(array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (is_string($c)) {
                $t = trim($c);
                if ($t !== '') {
                    return $t;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $itensRows
     * @return array<int>
     */
    public static function listarNumerosItensPncp(array $itensRows): array
    {
        $nums = [];
        foreach ($itensRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $n = (int) ($row['numeroItem'] ?? $row['numero_item'] ?? 0);
            if ($n > 0) {
                $nums[] = $n;
            }
        }
        sort($nums);

        return array_values(array_unique($nums));
    }

    /**
     * @param  array<int, array<string, mixed>>  $itensRows
     * @return array<string, mixed>|null
     */
    public static function encontrarItemPncpPorNumero(array $itensRows, int $numeroItem): ?array
    {
        foreach ($itensRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $n = (int) ($row['numeroItem'] ?? $row['numero_item'] ?? 0);
            if ($n === $numeroItem) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Extrai valores úteis para formação de preço / calculadora a partir de um item bruto do PNCP.
     *
     * @param  array<string, mixed>  $row
     * @return array{
     *   numero_item:int,
     *   valor_unitario_estimado:float|null,
     *   valor_total:float|null,
     *   quantidade:float|null,
     *   unidade_medida:?string,
     *   descricao_resumo:?string
     * }
     */
    public static function mapearReferenciaFormacaoPreco(array $row): array
    {
        $numero = (int) ($row['numeroItem'] ?? $row['numero_item'] ?? 0);

        $vu = $row['valorUnitarioEstimado'] ?? $row['valorUnitario'] ?? null;
        $vuF = is_numeric($vu) ? round((float) $vu, 4) : null;

        $q = $row['quantidade'] ?? null;
        $qF = is_numeric($q) ? (float) $q : null;

        $vt = $row['valorTotal'] ?? null;
        $vtF = is_numeric($vt) ? round((float) $vt, 4) : null;

        if ($vuF === null && $vtF !== null && $qF !== null && $qF > 0) {
            $vuF = round($vtF / $qF, 4);
        }
        if ($vtF === null && $vuF !== null && $qF !== null) {
            $vtF = round($vuF * $qF, 4);
        }

        $desc = isset($row['descricao']) ? trim((string) $row['descricao']) : '';
        if (mb_strlen($desc) > 400) {
            $desc = mb_substr($desc, 0, 397).'…';
        }

        $un = $row['unidadeMedida'] ?? $row['unidade_medida'] ?? null;
        $unStr = is_string($un) ? trim($un) : (is_scalar($un) ? trim((string) $un) : null);
        if ($unStr === '') {
            $unStr = null;
        }

        return [
            'numero_item' => $numero,
            'valor_unitario_estimado' => $vuF,
            'valor_total' => $vtF,
            'quantidade' => $qF,
            'unidade_medida' => $unStr,
            'descricao_resumo' => $desc !== '' ? $desc : null,
        ];
    }
}
