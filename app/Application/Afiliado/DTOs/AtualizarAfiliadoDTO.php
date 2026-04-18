<?php

declare(strict_types=1);

namespace App\Application\Afiliado\DTOs;

/**
 * DTO para atualização de Afiliado
 */
final class AtualizarAfiliadoDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $nome = null,
        public readonly ?string $documento = null,
        public readonly ?string $tipoDocumento = null,
        public readonly ?string $email = null,
        public readonly ?string $telefone = null,
        public readonly ?string $whatsapp = null,
        public readonly ?string $endereco = null,
        public readonly ?string $cidade = null,
        public readonly ?string $estado = null,
        public readonly ?string $cep = null,
        public readonly ?string $codigo = null,
        public readonly ?float $percentualDesconto = null,
        public readonly ?float $percentualComissao = null,
        public readonly ?string $banco = null,
        public readonly ?string $agencia = null,
        public readonly ?string $conta = null,
        public readonly ?string $tipoConta = null,
        public readonly ?string $pix = null,
        public readonly ?array $contasBancarias = null, // Array de contas bancárias
        public readonly ?bool $ativo = null,
        public readonly ?string $observacoes = null,
    ) {}

    public static function fromArray(int $id, array $data): self
    {
        return new self(
            id: $id,
            nome: $data['nome'] ?? null,
            documento: isset($data['documento']) ? preg_replace('/\D/', '', $data['documento']) : null,
            tipoDocumento: $data['tipo_documento'] ?? null,
            email: $data['email'] ?? null,
            telefone: $data['telefone'] ?? null,
            whatsapp: $data['whatsapp'] ?? null,
            endereco: $data['endereco'] ?? null,
            cidade: $data['cidade'] ?? null,
            estado: $data['estado'] ?? null,
            cep: $data['cep'] ?? null,
            codigo: $data['codigo'] ?? null,
            percentualDesconto: isset($data['percentual_desconto']) ? (float) $data['percentual_desconto'] : null,
            percentualComissao: isset($data['percentual_comissao']) ? (float) $data['percentual_comissao'] : null,
            banco: $data['banco'] ?? null,
            agencia: $data['agencia'] ?? null,
            conta: $data['conta'] ?? null,
            tipoConta: $data['tipo_conta'] ?? null,
            pix: $data['pix'] ?? null,
            contasBancarias: isset($data['contas_bancarias']) && is_array($data['contas_bancarias']) 
                ? $data['contas_bancarias'] 
                : null,
            ativo: isset($data['ativo']) ? (bool) $data['ativo'] : null,
            observacoes: $data['observacoes'] ?? null,
        );
    }

    public function toArray(): array
    {
        $data = [];
        
        if ($this->nome !== null) $data['nome'] = $this->nome;
        if ($this->documento !== null) $data['documento'] = $this->documento;
        if ($this->tipoDocumento !== null) $data['tipo_documento'] = $this->tipoDocumento;
        if ($this->email !== null) $data['email'] = $this->email;
        if ($this->telefone !== null) $data['telefone'] = $this->telefone;
        if ($this->whatsapp !== null) $data['whatsapp'] = $this->whatsapp;
        if ($this->endereco !== null) $data['endereco'] = $this->endereco;
        if ($this->cidade !== null) $data['cidade'] = $this->cidade;
        if ($this->estado !== null) $data['estado'] = $this->estado;
        if ($this->cep !== null) $data['cep'] = $this->cep;
        if ($this->codigo !== null) $data['codigo'] = $this->codigo;
        if ($this->percentualDesconto !== null) $data['percentual_desconto'] = $this->percentualDesconto;
        if ($this->percentualComissao !== null) $data['percentual_comissao'] = $this->percentualComissao;
        if ($this->banco !== null) $data['banco'] = $this->banco;
        if ($this->agencia !== null) $data['agencia'] = $this->agencia;
        if ($this->conta !== null) $data['conta'] = $this->conta;
        if ($this->tipoConta !== null) $data['tipo_conta'] = $this->tipoConta;
        if ($this->pix !== null) $data['pix'] = $this->pix;
        if ($this->contasBancarias !== null) $data['contas_bancarias'] = $this->contasBancarias;
        if ($this->ativo !== null) $data['ativo'] = $this->ativo;
        if ($this->observacoes !== null) $data['observacoes'] = $this->observacoes;
        
        return $data;
    }
}



