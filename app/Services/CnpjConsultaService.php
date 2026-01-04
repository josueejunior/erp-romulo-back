<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service para consulta de CNPJ na Receita Federal
 * 
 * Utiliza APIs públicas para buscar dados de empresas
 */
class CnpjConsultaService
{
    /**
     * URL base da API ReceitaWS (gratuita, 3 consultas/minuto)
     */
    protected string $receitaWsUrl = 'https://www.receitaws.com.br/v1/cnpj/';
    
    /**
     * URL base da API BrasilAPI (gratuita, ilimitada)
     */
    protected string $brasilApiUrl = 'https://brasilapi.com.br/api/cnpj/v1/';
    
    /**
     * Tempo de cache em segundos (24 horas)
     */
    protected int $cacheTtl = 86400;
    
    /**
     * Consultar CNPJ e retornar dados formatados
     */
    public function consultar(string $cnpj): ?array
    {
        $cnpj = $this->limparCnpj($cnpj);
        
        if (!$this->validarCnpj($cnpj)) {
            return null;
        }
        
        // Verificar cache primeiro
        $cacheKey = "cnpj_consulta_{$cnpj}";
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        // Tentar BrasilAPI primeiro (sem limite de requisições)
        $dados = $this->consultarBrasilApi($cnpj);
        
        // Se falhar, tentar ReceitaWS
        if (!$dados) {
            $dados = $this->consultarReceitaWs($cnpj);
        }
        
        if ($dados) {
            Cache::put($cacheKey, $dados, $this->cacheTtl);
        }
        
        return $dados;
    }
    
    /**
     * Consultar BrasilAPI
     */
    protected function consultarBrasilApi(string $cnpj): ?array
    {
        try {
            $response = Http::timeout(10)->get($this->brasilApiUrl . $cnpj);
            
            if (!$response->successful()) {
                Log::warning("BrasilAPI: Erro ao consultar CNPJ {$cnpj}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }
            
            $data = $response->json();
            
            return $this->formatarDadosBrasilApi($data);
            
        } catch (\Exception $e) {
            Log::warning("BrasilAPI: Exceção ao consultar CNPJ {$cnpj}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Consultar ReceitaWS
     */
    protected function consultarReceitaWs(string $cnpj): ?array
    {
        try {
            $response = Http::timeout(10)->get($this->receitaWsUrl . $cnpj);
            
            if (!$response->successful()) {
                Log::warning("ReceitaWS: Erro ao consultar CNPJ {$cnpj}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }
            
            $data = $response->json();
            
            if (isset($data['status']) && $data['status'] === 'ERROR') {
                Log::warning("ReceitaWS: CNPJ não encontrado {$cnpj}", [
                    'message' => $data['message'] ?? 'Erro desconhecido',
                ]);
                return null;
            }
            
            return $this->formatarDadosReceitaWs($data);
            
        } catch (\Exception $e) {
            Log::warning("ReceitaWS: Exceção ao consultar CNPJ {$cnpj}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * Formatar dados da BrasilAPI para o padrão do sistema
     */
    protected function formatarDadosBrasilApi(array $data): array
    {
        return [
            'cnpj' => $this->formatarCnpj($data['cnpj'] ?? ''),
            'razao_social' => $data['razao_social'] ?? '',
            'nome_fantasia' => $data['nome_fantasia'] ?? null,
            'situacao_cadastral' => $data['descricao_situacao_cadastral'] ?? '',
            'data_situacao_cadastral' => $data['data_situacao_cadastral'] ?? null,
            'cep' => $data['cep'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['municipio'] ?? null,
            'estado' => $data['uf'] ?? null,
            'telefone' => $data['ddd_telefone_1'] ?? null,
            'email' => $data['email'] ?? null,
            'natureza_juridica' => $data['natureza_juridica'] ?? null,
            'porte' => $data['porte'] ?? null,
            'capital_social' => $data['capital_social'] ?? null,
            'cnae_principal' => [
                'codigo' => $data['cnae_fiscal'] ?? null,
                'descricao' => $data['cnae_fiscal_descricao'] ?? null,
            ],
            'fonte' => 'brasil_api',
        ];
    }
    
    /**
     * Formatar dados da ReceitaWS para o padrão do sistema
     */
    protected function formatarDadosReceitaWs(array $data): array
    {
        return [
            'cnpj' => $this->formatarCnpj($data['cnpj'] ?? ''),
            'razao_social' => $data['nome'] ?? '',
            'nome_fantasia' => $data['fantasia'] ?? null,
            'situacao_cadastral' => $data['situacao'] ?? '',
            'data_situacao_cadastral' => $data['data_situacao'] ?? null,
            'cep' => $data['cep'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['municipio'] ?? null,
            'estado' => $data['uf'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'email' => $data['email'] ?? null,
            'natureza_juridica' => $data['natureza_juridica'] ?? null,
            'porte' => $data['porte'] ?? null,
            'capital_social' => isset($data['capital_social']) ? (float) $data['capital_social'] : null,
            'cnae_principal' => [
                'codigo' => $data['atividade_principal'][0]['code'] ?? null,
                'descricao' => $data['atividade_principal'][0]['text'] ?? null,
            ],
            'fonte' => 'receita_ws',
        ];
    }
    
    /**
     * Limpar CNPJ (remover formatação)
     */
    protected function limparCnpj(string $cnpj): string
    {
        return preg_replace('/\D/', '', $cnpj);
    }
    
    /**
     * Formatar CNPJ com pontuação
     */
    protected function formatarCnpj(string $cnpj): string
    {
        $cnpj = $this->limparCnpj($cnpj);
        
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }
        
        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2)
        );
    }
    
    /**
     * Validar CNPJ
     */
    public function validarCnpj(string $cnpj): bool
    {
        $cnpj = $this->limparCnpj($cnpj);
        
        if (strlen($cnpj) !== 14) {
            return false;
        }
        
        // Verificar se todos os dígitos são iguais (CNPJs inválidos conhecidos)
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        
        // Validar dígitos verificadores
        $tamanho = strlen($cnpj) - 2;
        $numeros = substr($cnpj, 0, $tamanho);
        $digitos = substr($cnpj, $tamanho);
        
        // Primeiro dígito verificador
        $soma = 0;
        $pos = $tamanho - 7;
        for ($i = $tamanho; $i >= 1; $i--) {
            $soma += $numeros[$tamanho - $i] * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }
        $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
        if ($resultado != $digitos[0]) {
            return false;
        }
        
        // Segundo dígito verificador
        $tamanho = $tamanho + 1;
        $numeros = substr($cnpj, 0, $tamanho);
        $soma = 0;
        $pos = $tamanho - 7;
        for ($i = $tamanho; $i >= 1; $i--) {
            $soma += $numeros[$tamanho - $i] * $pos--;
            if ($pos < 2) {
                $pos = 9;
            }
        }
        $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
        
        return $resultado == $digitos[1];
    }
}
