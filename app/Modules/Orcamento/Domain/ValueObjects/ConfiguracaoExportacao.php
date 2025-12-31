<?php

namespace App\Modules\Orcamento\Domain\ValueObjects;

class ConfiguracaoExportacao
{
    private string $formato;
    private array $campos;
    private ?string $filtros;
    private bool $incluirDetalhes;

    private const FORMATOS_VALIDOS = ['excel', 'pdf', 'csv'];

    public function __construct(
        string $formato,
        array $campos = [],
        ?string $filtros = null,
        bool $incluirDetalhes = true
    ) {
        if (!in_array($formato, self::FORMATOS_VALIDOS)) {
            throw new \InvalidArgumentException('Formato inválido: ' . $formato);
        }

        $this->formato = $formato;
        $this->campos = empty($campos) ? $this->getCamposPadrao() : $campos;
        $this->filtros = $filtros;
        $this->incluirDetalhes = $incluirDetalhes;
    }

    public function getFormato(): string
    {
        return $this->formato;
    }

    public function getCampos(): array
    {
        return $this->campos;
    }

    public function getFiltros(): ?string
    {
        return $this->filtros;
    }

    public function isIncluirDetalhes(): bool
    {
        return $this->incluirDetalhes;
    }

    public function getMimeType(): string
    {
        return match($this->formato) {
            'excel' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            default => 'application/octet-stream'
        };
    }

    public function getExtensao(): string
    {
        return match($this->formato) {
            'excel' => 'xlsx',
            'pdf' => 'pdf',
            'csv' => 'csv',
            default => 'bin'
        };
    }

    private function getCamposPadrao(): array
    {
        return [
            'id',
            'fornecedor',
            'processo',
            'valor_total',
            'status',
            'data_criacao'
        ];
    }

    public function toArray(): array
    {
        return [
            'formato' => $this->formato,
            'campos' => $this->campos,
            'filtros' => $this->filtros,
            'incluir_detalhes' => $this->incluirDetalhes,
            'mime_type' => $this->getMimeType(),
            'extensao' => $this->getExtensao()
        ];
    }
}
