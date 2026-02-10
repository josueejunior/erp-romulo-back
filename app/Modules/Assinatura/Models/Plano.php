<?php

namespace App\Modules\Assinatura\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasTimestampsCustomizados;

/**
 * Model para planos de assinatura
 * 
 * 🔥 IMPORTANTE: Esta tabela está no banco CENTRAL, não no banco do tenant
 */
class Plano extends BaseModel
{
    /**
     * 🔥 IMPORTANTE: Sempre usar conexão central, mesmo quando no contexto do tenant
     * Esta tabela está no banco central, não no banco do tenant
     */
    protected $connection = 'pgsql';
    
    use HasTimestampsCustomizados;
    
    public $timestamps = true;

    protected $fillable = [
        'nome',
        'descricao',
        'preco_mensal',
        'preco_anual',
        'percentual_comissao_afiliado', // Percentual de comissão do afiliado (40%, 60%, 100%)
        'limite_processos',
        'restricao_diaria',
        'limite_usuarios',
        'limite_armazenamento_mb',
        'recursos_disponiveis',
        'ativo',
        'ordem',
    ];

    /**
     * Atributos calculados que devem aparecer automaticamente no JSON da API.
     * Mantém a compatibilidade com o frontend atual, mas centraliza a regra
     * de preço promocional (ex.: 50% OFF) no backend.
     */
    protected $appends = [
        'preco_promocional_mensal',
        'preco_promocional_anual',
    ];

    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'preco_mensal' => 'decimal:2',
            'preco_anual' => 'decimal:2',
            'percentual_comissao_afiliado' => 'decimal:2',
            'limite_processos' => 'integer',
            'restricao_diaria' => 'boolean',
            'limite_usuarios' => 'integer',
            'limite_armazenamento_mb' => 'integer',
            'recursos_disponiveis' => 'array',
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ]);
    }

    public function assinaturas()
    {
        return $this->hasMany(Assinatura::class);
    }

    /**
     * Verifica se o plano está ativo
     */
    public function isAtivo(): bool
    {
        return $this->ativo === true;
    }

    /**
     * Verifica se o plano tem limite ilimitado para processos
     */
    public function temProcessosIlimitados(): bool
    {
        return $this->limite_processos === null;
    }

    /**
     * Verifica se o plano tem limite ilimitado para usuários
     */
    public function temUsuariosIlimitados(): bool
    {
        return $this->limite_usuarios === null;
    }

    /**
     * Verifica se o plano tem restrição diária (1 processo por dia)
     */
    public function temRestricaoDiaria(): bool
    {
        return $this->restricao_diaria === true;
    }

    /**
     * Calcula o preço final do plano com base no período e regras de negócio.
     * Centraliza a lógica de desconto promocional (ex.: 50% OFF).
     */
    public function calcularPreco(string $periodo, int $meses = 1): float
    {
        // Regra de negócio: 50% de desconto promocional em todos os planos
        $descontoPromocional = 0.5;

        if ($periodo === 'anual' || $meses === 12) {
            // Se o plano tiver preco_anual no DB, usa ele como base.
            // Senão usa mensal * 10 (regra de 2 meses grátis no anual)
            $precoBaseAnual = $this->preco_anual ?: ($this->preco_mensal * 10);
            return round($precoBaseAnual * $descontoPromocional, 2);
        }

        // Mensal (multiplicado por quantidade de meses, se necessário)
        return round($this->preco_mensal * $descontoPromocional * $meses, 2);
    }

    /**
     * Preço mensal promocional (usado na tela de planos).
     * Ex.: valor de tabela R$ 277,14 → R$ 138,57 com 50% OFF.
     */
    public function getPrecoPromocionalMensalAttribute(): ?float
    {
        if ($this->preco_mensal === null) {
            return null;
        }

        return $this->calcularPreco('mensal', 1);
    }

    /**
     * Preço anual promocional (usado na tela de planos).
     * Considera as regras de anual (ex.: 2 meses grátis).
     */
    public function getPrecoPromocionalAnualAttribute(): ?float
    {
        if ($this->preco_mensal === null && $this->preco_anual === null) {
            return null;
        }

        return $this->calcularPreco('anual', 12);
    }
}

