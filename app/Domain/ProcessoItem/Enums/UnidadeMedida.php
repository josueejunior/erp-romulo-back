<?php

namespace App\Domain\ProcessoItem\Enums;

/**
 * Enum de Unidades de Medida para Itens de Processo
 */
enum UnidadeMedida: string
{
    case UNIDADE = 'unidade';
    case PACOTE = 'pacote';
    case CAIXA = 'caixa';
    case LITRO = 'litro';
    case METRO = 'metro';
    case METRO_QUADRADO = 'metro_quadrado';
    case METRO_CUBICO = 'metro_cubico';
    case QUILOGRAMA = 'quilograma';
    case GRAMA = 'grama';
    case TONELADA = 'tonelada';
    case MES = 'mes';
    case ANO = 'ano';
    case DIA = 'dia';
    case HORA = 'hora';
    case SERVICO = 'servico';
    case GLOBAL = 'global';
    case CONJUNTO = 'conjunto';
    case PAR = 'par';
    case DUZIA = 'duzia';
    case ROLO = 'rolo';
    case GALAO = 'galao';
    case FRASCO = 'frasco';
    case AMPOLA = 'ampola';
    case COMPRIMIDO = 'comprimido';
    case OUTRO = 'outro';
    
    /**
     * Retorna o label amigável da unidade
     */
    public function label(): string
    {
        return match($this) {
            self::UNIDADE => 'Unidade',
            self::PACOTE => 'Pacote',
            self::CAIXA => 'Caixa',
            self::LITRO => 'Litro',
            self::METRO => 'Metro',
            self::METRO_QUADRADO => 'Metro Quadrado (m²)',
            self::METRO_CUBICO => 'Metro Cúbico (m³)',
            self::QUILOGRAMA => 'Quilograma (kg)',
            self::GRAMA => 'Grama (g)',
            self::TONELADA => 'Tonelada',
            self::MES => 'Mês',
            self::ANO => 'Ano',
            self::DIA => 'Dia',
            self::HORA => 'Hora',
            self::SERVICO => 'Serviço',
            self::GLOBAL => 'Global',
            self::CONJUNTO => 'Conjunto',
            self::PAR => 'Par',
            self::DUZIA => 'Dúzia',
            self::ROLO => 'Rolo',
            self::GALAO => 'Galão',
            self::FRASCO => 'Frasco',
            self::AMPOLA => 'Ampola',
            self::COMPRIMIDO => 'Comprimido',
            self::OUTRO => 'Outro',
        };
    }
    
    /**
     * Retorna abreviação da unidade
     */
    public function abreviacao(): string
    {
        return match($this) {
            self::UNIDADE => 'un',
            self::PACOTE => 'pct',
            self::CAIXA => 'cx',
            self::LITRO => 'L',
            self::METRO => 'm',
            self::METRO_QUADRADO => 'm²',
            self::METRO_CUBICO => 'm³',
            self::QUILOGRAMA => 'kg',
            self::GRAMA => 'g',
            self::TONELADA => 't',
            self::MES => 'mês',
            self::ANO => 'ano',
            self::DIA => 'dia',
            self::HORA => 'h',
            self::SERVICO => 'serv',
            self::GLOBAL => 'glob',
            self::CONJUNTO => 'conj',
            self::PAR => 'par',
            self::DUZIA => 'dz',
            self::ROLO => 'rolo',
            self::GALAO => 'gal',
            self::FRASCO => 'fr',
            self::AMPOLA => 'amp',
            self::COMPRIMIDO => 'comp',
            self::OUTRO => '-',
        };
    }
    
    /**
     * Retorna todas as unidades como array para dropdown
     */
    public static function toArray(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'abreviacao' => $case->abreviacao(),
        ], self::cases());
    }
    
    /**
     * Retorna valores válidos para validação
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
