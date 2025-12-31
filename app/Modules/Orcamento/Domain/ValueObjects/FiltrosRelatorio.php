<?php

namespace App\Modules\Orcamento\Domain\ValueObjects;

class FiltrosRelatorio
{
    private ?string $dataInicio;
    private ?string $dataFim;
    private ?int $fornecedorId;
    private ?int $processoId;
    private ?string $status;
    private ?string $ordenacao;

    public function __construct(
        ?string $dataInicio = null,
        ?string $dataFim = null,
        ?int $fornecedorId = null,
        ?int $processoId = null,
        ?string $status = null,
        ?string $ordenacao = 'created_at_desc'
    ) {
        $this->dataInicio = $dataInicio;
        $this->dataFim = $dataFim;
        $this->fornecedorId = $fornecedorId;
        $this->processoId = $processoId;
        $this->status = $status;
        $this->ordenacao = $ordenacao;
    }

    public function getDataInicio(): ?string
    {
        return $this->dataInicio;
    }

    public function getDataFim(): ?string
    {
        return $this->dataFim;
    }

    public function getFornecedorId(): ?int
    {
        return $this->fornecedorId;
    }

    public function getProcessoId(): ?int
    {
        return $this->processoId;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getOrdenacao(): ?string
    {
        return $this->ordenacao;
    }

    public function toArray(): array
    {
        return [
            'data_inicio' => $this->dataInicio,
            'data_fim' => $this->dataFim,
            'fornecedor_id' => $this->fornecedorId,
            'processo_id' => $this->processoId,
            'status' => $this->status,
            'ordenacao' => $this->ordenacao
        ];
    }
}
