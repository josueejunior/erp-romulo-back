<?php

namespace App\Modules\Assinatura\Models;

use App\Models\BaseModel;
use App\Models\Traits\HasTimestampsCustomizados;
use Illuminate\Support\Str;

class Plano extends BaseModel
{
    use HasTimestampsCustomizados;

    // planos é uma tabela central (não por tenant)
    protected $connection = 'pgsql';

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
        if ($this->limite_processos === null) {
            return true;
        }

        // Fallback para dados legados inconsistentes:
        // se o nome do plano for "Ilimitado", tratar como ilimitado.
        return Str::contains(Str::lower((string) $this->nome), 'ilimitado');
    }

    /**
     * Verifica se o plano tem limite ilimitado para usuários
     */
    public function temUsuariosIlimitados(): bool
    {
        if ($this->limite_usuarios === null) {
            return true;
        }

        return Str::contains(Str::lower((string) $this->nome), 'ilimitado');
    }

    /**
     * Verifica se o plano tem restrição diária (1 processo por dia)
     */
    public function temRestricaoDiaria(): bool
    {
        if ($this->temProcessosIlimitados()) {
            return false;
        }

        return $this->restricao_diaria === true;
    }
    /**
     * Calcula o preço final do plano com base no período e regras de negócio
     * Centraliza a lógica de 50% de desconto promocional
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

        // Mensal
        return round($this->preco_mensal * $descontoPromocional * $meses, 2);
    }
}
