<?php

declare(strict_types=1);

namespace App\Application\Afiliado\DTOs;

/**
 * DTO para criação de Afiliado
 */
final class CriarAfiliadoDTO
{
    public function __construct(
        public readonly string $nome,
        public readonly string $documento,
        public readonly string $tipoDocumento,
        public readonly string $email,
        public readonly ?string $telefone = null,
        public readonly ?string $whatsapp = null,
        public readonly ?string $endereco = null,
        public readonly ?string $cidade = null,
        public readonly ?string $estado = null,
        public readonly ?string $cep = null,
        public readonly ?string $codigo = null,
        public readonly float $percentualDesconto = 0.0,
        public readonly float $percentualComissao = 0.0,
        public readonly ?string $banco = null,
        public readonly ?string $agencia = null,
        public readonly ?string $conta = null,
        public readonly ?string $tipoConta = null,
        public readonly ?string $pix = null,
        public readonly ?array $contasBancarias = null, // Array de contas bancárias
        public readonly bool $ativo = true,
        public readonly ?string $observacoes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            nome: $data['nome'] ?? '',
            documento: preg_replace('/\D/', '', $data['documento'] ?? ''),
            tipoDocumento: $data['tipo_documento'] ?? 'cpf',
            email: $data['email'] ?? '',
            telefone: $data['telefone'] ?? null,
            whatsapp: $data['whatsapp'] ?? null,
            endereco: $data['endereco'] ?? null,
            cidade: $data['cidade'] ?? null,
            estado: $data['estado'] ?? null,
            cep: $data['cep'] ?? null,
            codigo: $data['codigo'] ?? null,
            percentualDesconto: (float) ($data['percentual_desconto'] ?? 0),
            percentualComissao: (float) ($data['percentual_comissao'] ?? 0),
            banco: $data['banco'] ?? null,
            agencia: $data['agencia'] ?? null,
            conta: $data['conta'] ?? null,
            tipoConta: $data['tipo_conta'] ?? null,
            pix: $data['pix'] ?? null,
            contasBancarias: isset($data['contas_bancarias']) && is_array($data['contas_bancarias']) 
                ? $data['contas_bancarias'] 
                : null,
            ativo: $data['ativo'] ?? true,
            observacoes: $data['observacoes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'nome' => $this->nome,
            'documento' => $this->documento,
            'tipo_documento' => $this->tipoDocumento,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'whatsapp' => $this->whatsapp,
            'endereco' => $this->endereco,
            'cidade' => $this->cidade,
            'estado' => $this->estado,
            'cep' => $this->cep,
            'codigo' => $this->codigo,
            'percentual_desconto' => $this->percentualDesconto,
            'percentual_comissao' => $this->percentualComissao,
            'banco' => $this->banco,
            'agencia' => $this->agencia,
            'conta' => $this->conta,
            'tipo_conta' => $this->tipoConta,
            'pix' => $this->pix,
            'contas_bancarias' => $this->contasBancarias,
            'ativo' => $this->ativo,
            'observacoes' => $this->observacoes,
        ];
    }
}



