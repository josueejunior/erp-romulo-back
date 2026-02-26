<?php

namespace App\Domain\Tenant\Entities;

use App\Domain\Exceptions\DomainException;

/**
 * Entidade Tenant - Representa uma empresa/tenant no domínio
 * Contém apenas regras de negócio, sem dependências de infraestrutura
 */
class Tenant
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $razaoSocial,
        public readonly ?string $nomeFantasia = null,
        public readonly ?string $cnpj,
        public readonly ?string $email,
        public readonly string $status = 'ativa',
        public readonly ?string $endereco = null,
        public readonly ?string $cidade = null,
        public readonly ?string $estado = null,
        public readonly ?string $cep = null,
        public readonly ?array $telefones = null,
        public readonly ?array $emailsAdicionais = null,
        public readonly ?string $banco = null,
        public readonly ?string $agencia = null,
        public readonly ?string $conta = null,
        public readonly ?string $tipoConta = null,
        public readonly ?string $pix = null,
        public readonly ?string $representanteLegalNome = null,
        public readonly ?string $representanteLegalCpf = null,
        public readonly ?string $representanteLegalCargo = null,
        public readonly ?string $logo = null,
        public readonly ?string $telefoneFixo = null,
        public readonly ?string $emailFinanceiro = null,
        public readonly ?string $emailLicitacao = null,
        public readonly ?string $inscricaoEstadual = null,
        public readonly ?string $inscricaoMunicipal = null,
        public readonly ?string $cnaePrincipal = null,
        public readonly ?string $dataAbertura = null,
        public readonly ?string $representanteLegalRg = null,
        public readonly ?string $representanteLegalTelefone = null,
        public readonly ?string $representanteLegalEmail = null,
        public readonly ?string $favorecidoRazaoSocial = null,
        public readonly ?string $favorecidoCnpj = null,
        public readonly ?string $responsavelComercial = null,
        public readonly ?string $responsavelFinanceiro = null,
        public readonly ?string $responsavelLicitacoes = null,
        public readonly ?string $ramoAtuacao = null,
        public readonly ?string $principaisProdutosServicos = null,
        public readonly ?string $marcasTrabalhadas = null,
        public readonly ?string $observacoes = null,
        public readonly ?string $site = null,
    ) {
        $this->validate();
    }

    /**
     * Validações de negócio da entidade Tenant
     */
    private function validate(): void
    {
        if (empty(trim($this->razaoSocial))) {
            throw new DomainException('A razão social é obrigatória.');
        }

        if ($this->nomeFantasia !== null && strlen($this->nomeFantasia) > 255) {
            throw new DomainException('O nome fantasia não pode ter mais de 255 caracteres.');
        }

        if (strlen($this->razaoSocial) > 255) {
            throw new DomainException('A razão social não pode ter mais de 255 caracteres.');
        }

        if ($this->cnpj !== null && strlen($this->cnpj) > 18) {
            throw new DomainException('O CNPJ não pode ter mais de 18 caracteres.');
        }

        if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('O e-mail fornecido é inválido.');
        }

        if (!in_array($this->status, ['pending', 'processing', 'ativa', 'inativa', 'failed'])) {
            throw new DomainException('O status deve ser "pending", "processing", "ativa", "inativa" ou "failed".');
        }

        if ($this->estado !== null && strlen($this->estado) !== 2) {
            throw new DomainException('O estado deve ter exatamente 2 caracteres.');
        }
    }

    /**
     * Regra de negócio: CNPJ não pode ser alterado se já existe um definido
     */
    public function podeAlterarCnpj(?string $novoCnpj): bool
    {
        if ($this->cnpj && $novoCnpj && $novoCnpj !== $this->cnpj) {
            return false;
        }
        
        return true;
    }

    /**
     * Regra de negócio: Inativar tenant
     */
    public function inativar(): self
    {
        if ($this->status === 'inativa') {
            return $this;
        }

        return new self(
            id: $this->id,
            razaoSocial: $this->razaoSocial,
            nomeFantasia: $this->nomeFantasia,
            cnpj: $this->cnpj,
            email: $this->email,
            status: 'inativa',
            endereco: $this->endereco,
            cidade: $this->cidade,
            estado: $this->estado,
            cep: $this->cep,
            telefones: $this->telefones,
            emailsAdicionais: $this->emailsAdicionais,
            banco: $this->banco,
            agencia: $this->agencia,
            conta: $this->conta,
            tipoConta: $this->tipoConta,
            pix: $this->pix,
            representanteLegalNome: $this->representanteLegalNome,
            representanteLegalCpf: $this->representanteLegalCpf,
            representanteLegalCargo: $this->representanteLegalCargo,
            logo: $this->logo,
            telefoneFixo: $this->telefoneFixo,
            emailFinanceiro: $this->emailFinanceiro,
            emailLicitacao: $this->emailLicitacao,
            inscricaoEstadual: $this->inscricaoEstadual,
            inscricaoMunicipal: $this->inscricaoMunicipal,
            cnaePrincipal: $this->cnaePrincipal,
            dataAbertura: $this->dataAbertura,
            representanteLegalRg: $this->representanteLegalRg,
            representanteLegalTelefone: $this->representanteLegalTelefone,
            representanteLegalEmail: $this->representanteLegalEmail,
            favorecidoRazaoSocial: $this->favorecidoRazaoSocial,
            favorecidoCnpj: $this->favorecidoCnpj,
            responsavelComercial: $this->responsavelComercial,
            responsavelFinanceiro: $this->responsavelFinanceiro,
            responsavelLicitacoes: $this->responsavelLicitacoes,
            ramoAtuacao: $this->ramoAtuacao,
            principaisProdutosServicos: $this->principaisProdutosServicos,
            marcasTrabalhadas: $this->marcasTrabalhadas,
            observacoes: $this->observacoes,
            site: $this->site,
        );
    }

    /**
     * Regra de negócio: Reativar tenant
     */
    public function reativar(): self
    {
        return new self(
            id: $this->id,
            razaoSocial: $this->razaoSocial,
            nomeFantasia: $this->nomeFantasia,
            cnpj: $this->cnpj,
            email: $this->email,
            status: 'ativa',
            endereco: $this->endereco,
            cidade: $this->cidade,
            estado: $this->estado,
            cep: $this->cep,
            telefones: $this->telefones,
            emailsAdicionais: $this->emailsAdicionais,
            banco: $this->banco,
            agencia: $this->agencia,
            conta: $this->conta,
            tipoConta: $this->tipoConta,
            pix: $this->pix,
            representanteLegalNome: $this->representanteLegalNome,
            representanteLegalCpf: $this->representanteLegalCpf,
            representanteLegalCargo: $this->representanteLegalCargo,
            logo: $this->logo,
            telefoneFixo: $this->telefoneFixo,
            emailFinanceiro: $this->emailFinanceiro,
            emailLicitacao: $this->emailLicitacao,
            inscricaoEstadual: $this->inscricaoEstadual,
            inscricaoMunicipal: $this->inscricaoMunicipal,
            cnaePrincipal: $this->cnaePrincipal,
            dataAbertura: $this->dataAbertura,
            representanteLegalRg: $this->representanteLegalRg,
            representanteLegalTelefone: $this->representanteLegalTelefone,
            representanteLegalEmail: $this->representanteLegalEmail,
            favorecidoRazaoSocial: $this->favorecidoRazaoSocial,
            favorecidoCnpj: $this->favorecidoCnpj,
            responsavelComercial: $this->responsavelComercial,
            responsavelFinanceiro: $this->responsavelFinanceiro,
            responsavelLicitacoes: $this->responsavelLicitacoes,
            ramoAtuacao: $this->ramoAtuacao,
            principaisProdutosServicos: $this->principaisProdutosServicos,
            marcasTrabalhadas: $this->marcasTrabalhadas,
            observacoes: $this->observacoes,
            site: $this->site,
        );
    }

    /**
     * Verifica se o tenant está ativo
     */
    public function estaAtivo(): bool
    {
        return $this->status === 'ativa';
    }

    /**
     * 🔥 DDD: Método imutável para atualizar tenant
     * Retorna nova instância com campos atualizados
     * 
     * @param array $updates Array com campos a atualizar (snake_case ou camelCase)
     * @return self Nova instância com atualizações
     */
    /**
     * 🔥 DDD: Método imutável para atualizar tenant
     * Retorna nova instância com campos atualizados
     * 
     * @param array $updates Array com campos a atualizar (snake_case ou camelCase)
     * @return self Nova instância com atualizações
     */
    public function withUpdates(array $updates): self
    {
        // Função helper para converter snake_case para camelCase
        $toCamelCase = function(string $string): string {
            return lcfirst(str_replace('_', '', ucwords($string, '_')));
        };

        // Normalizar keys - aceita tanto snake_case quanto camelCase
        $normalized = [];
        foreach ($updates as $key => $value) {
            // Converter snake_case para camelCase
            $camelKey = $toCamelCase($key);
            $normalized[$camelKey] = $value;
            // Manter também a key original (pode ser snake_case ou camelCase)
            $normalized[$key] = $value;
        }

        return new self(
            id: $this->id,
            razaoSocial: $normalized['razaoSocial'] ?? $normalized['razao_social'] ?? $this->razaoSocial,
            nomeFantasia: $normalized['nomeFantasia'] ?? $normalized['nome_fantasia'] ?? $this->nomeFantasia,
            cnpj: $normalized['cnpj'] ?? $this->cnpj,
            email: $normalized['email'] ?? $this->email,
            status: $normalized['status'] ?? $this->status,
            endereco: $normalized['endereco'] ?? $this->endereco,
            cidade: $normalized['cidade'] ?? $this->cidade,
            estado: $normalized['estado'] ?? $this->estado,
            cep: $normalized['cep'] ?? $this->cep,
            telefones: $normalized['telefones'] ?? $this->telefones,
            emailsAdicionais: $normalized['emailsAdicionais'] ?? $normalized['emails_adicionais'] ?? $this->emailsAdicionais,
            banco: $normalized['banco'] ?? $this->banco,
            agencia: $normalized['agencia'] ?? $this->agencia,
            conta: $normalized['conta'] ?? $this->conta,
            tipoConta: $normalized['tipoConta'] ?? $normalized['tipo_conta'] ?? $this->tipoConta,
            pix: $normalized['pix'] ?? $this->pix,
            representanteLegalNome: $normalized['representanteLegalNome'] ?? $normalized['representante_legal_nome'] ?? $this->representanteLegalNome,
            representanteLegalCpf: $normalized['representanteLegalCpf'] ?? $normalized['representante_legal_cpf'] ?? $this->representanteLegalCpf,
            representanteLegalCargo: $normalized['representanteLegalCargo'] ?? $normalized['representante_legal_cargo'] ?? $this->representanteLegalCargo,
            logo: $normalized['logo'] ?? $this->logo,
            telefoneFixo: $normalized['telefoneFixo'] ?? $normalized['telefone_fixo'] ?? $this->telefoneFixo,
            emailFinanceiro: $normalized['emailFinanceiro'] ?? $normalized['email_financeiro'] ?? $this->emailFinanceiro,
            emailLicitacao: $normalized['emailLicitacao'] ?? $normalized['email_licitacao'] ?? $this->emailLicitacao,
            inscricaoEstadual: $normalized['inscricaoEstadual'] ?? $normalized['inscricao_estadual'] ?? $this->inscricaoEstadual,
            inscricaoMunicipal: $normalized['inscricaoMunicipal'] ?? $normalized['inscricao_municipal'] ?? $this->inscricaoMunicipal,
            cnaePrincipal: $normalized['cnaePrincipal'] ?? $normalized['cnae_principal'] ?? $this->cnaePrincipal,
            dataAbertura: $normalized['dataAbertura'] ?? $normalized['data_abertura'] ?? $this->dataAbertura,
            representanteLegalRg: $normalized['representanteLegalRg'] ?? $normalized['representante_legal_rg'] ?? $this->representanteLegalRg,
            representanteLegalTelefone: $normalized['representanteLegalTelefone'] ?? $normalized['representante_legal_telefone'] ?? $this->representanteLegalTelefone,
            representanteLegalEmail: $normalized['representanteLegalEmail'] ?? $normalized['representante_legal_email'] ?? $this->representanteLegalEmail,
            favorecidoRazaoSocial: $normalized['favorecidoRazaoSocial'] ?? $normalized['favorecido_razao_social'] ?? $this->favorecidoRazaoSocial,
            favorecidoCnpj: $normalized['favorecidoCnpj'] ?? $normalized['favorecido_cnpj'] ?? $this->favorecidoCnpj,
            responsavelComercial: $normalized['responsavelComercial'] ?? $normalized['responsavel_comercial'] ?? $this->responsavelComercial,
            responsavelFinanceiro: $normalized['responsavelFinanceiro'] ?? $normalized['responsavel_financeiro'] ?? $this->responsavelFinanceiro,
            responsavelLicitacoes: $normalized['responsavelLicitacoes'] ?? $normalized['responsavel_licitacoes'] ?? $this->responsavelLicitacoes,
            ramoAtuacao: $normalized['ramoAtuacao'] ?? $normalized['ramo_atuacao'] ?? $this->ramoAtuacao,
            principaisProdutosServicos: $normalized['principaisProdutosServicos'] ?? $normalized['principais_produtos_servicos'] ?? $this->principaisProdutosServicos,
            marcasTrabalhadas: $normalized['marcasTrabalhadas'] ?? $normalized['marcas_trabalhadas'] ?? $this->marcasTrabalhadas,
            observacoes: $normalized['observacoes'] ?? $this->observacoes,
            site: $normalized['site'] ?? $this->site,
        );
    }
}




