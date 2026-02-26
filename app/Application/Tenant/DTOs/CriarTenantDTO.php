<?php

namespace App\Application\Tenant\DTOs;

/**
 * DTO (Data Transfer Object) para criação de tenant
 * Transporta dados entre camadas sem expor entidades do domínio
 */
class CriarTenantDTO
{
    public function __construct(
        public readonly string $razaoSocial,
        public readonly ?string $nomeFantasia = null,
        public readonly ?string $cnpj = null,
        public readonly ?string $email = null,
        public readonly string $status = 'ativa',
        public readonly ?string $endereco = null,
        public readonly ?string $cidade = null,
        public readonly ?string $estado = null,
        public readonly ?string $cep = null,
        public readonly ?array $telefones = null,
        public readonly ?array $emailsAdicionais = null,
        public readonly ?string $telefoneFixo = null,
        public readonly ?string $emailFinanceiro = null,
        public readonly ?string $emailLicitacao = null,
        public readonly ?string $banco = null,
        public readonly ?string $agencia = null,
        public readonly ?string $conta = null,
        public readonly ?string $tipoConta = null,
        public readonly ?string $pix = null,
        public readonly ?string $representanteLegalNome = null,
        public readonly ?string $representanteLegalCpf = null,
        public readonly ?string $representanteLegalCargo = null,
        public readonly ?string $representanteLegalRg = null,
        public readonly ?string $representanteLegalTelefone = null,
        public readonly ?string $representanteLegalEmail = null,
        public readonly ?string $favorecidoRazaoSocial = null,
        public readonly ?string $favorecidoCnpj = null,
        public readonly ?string $inscricaoEstadual = null,
        public readonly ?string $inscricaoMunicipal = null,
        public readonly ?string $cnaePrincipal = null,
        public readonly ?string $dataAbertura = null,
        public readonly ?string $responsavelComercial = null,
        public readonly ?string $responsavelFinanceiro = null,
        public readonly ?string $responsavelLicitacoes = null,
        public readonly ?string $ramoAtuacao = null,
        public readonly ?string $principaisProdutosServicos = null,
        public readonly ?string $marcasTrabalhadas = null,
        public readonly ?string $observacoes = null,
        public readonly ?string $site = null,
        public readonly ?string $logo = null,
        // Dados do administrador (opcional)
        public readonly ?string $adminName = null,
        public readonly ?string $adminEmail = null,
        public readonly ?string $adminPassword = null,
    ) {}

    /**
     * Criar DTO a partir de array (vindo do request)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            razaoSocial: $data['razao_social'],
            nomeFantasia: $data['nome_fantasia'] ?? null,
            cnpj: $data['cnpj'] ?? null,
            email: $data['email'] ?? null,
            status: $data['status'] ?? 'ativa',
            endereco: $data['endereco'] ?? null,
            cidade: $data['cidade'] ?? null,
            estado: $data['estado'] ?? null,
            cep: $data['cep'] ?? null,
            telefones: $data['telefones'] ?? null,
            emailsAdicionais: $data['emails_adicionais'] ?? null,
            telefoneFixo: $data['telefone_fixo'] ?? null,
            emailFinanceiro: $data['email_financeiro'] ?? null,
            emailLicitacao: $data['email_licitacao'] ?? null,
            banco: $data['banco'] ?? null,
            agencia: $data['agencia'] ?? null,
            conta: $data['conta'] ?? null,
            tipoConta: $data['tipo_conta'] ?? null,
            pix: $data['pix'] ?? null,
            representanteLegalNome: $data['representante_legal_nome'] ?? null,
            representanteLegalCpf: $data['representante_legal_cpf'] ?? null,
            representanteLegalCargo: $data['representante_legal_cargo'] ?? null,
            representanteLegalRg: $data['representante_legal_rg'] ?? null,
            representanteLegalTelefone: $data['representante_legal_telefone'] ?? null,
            representanteLegalEmail: $data['representante_legal_email'] ?? null,
            favorecidoRazaoSocial: $data['favorecido_razao_social'] ?? null,
            favorecidoCnpj: $data['favorecido_cnpj'] ?? null,
            inscricaoEstadual: $data['inscricao_estadual'] ?? null,
            inscricaoMunicipal: $data['inscricao_municipal'] ?? null,
            cnaePrincipal: $data['cnae_principal'] ?? null,
            dataAbertura: $data['data_abertura'] ?? null,
            responsavelComercial: $data['responsavel_comercial'] ?? null,
            responsavelFinanceiro: $data['responsavel_financeiro'] ?? null,
            responsavelLicitacoes: $data['responsavel_licitacoes'] ?? null,
            ramoAtuacao: $data['ramo_atuacao'] ?? null,
            principaisProdutosServicos: $data['principais_produtos_servicos'] ?? null,
            marcasTrabalhadas: $data['marcas_trabalhadas'] ?? null,
            observacoes: $data['observacoes'] ?? null,
            site: $data['site'] ?? null,
            logo: $data['logo'] ?? null,
            adminName: $data['admin_name'] ?? null,
            adminEmail: $data['admin_email'] ?? null,
            adminPassword: $data['admin_password'] ?? null,
        );
    }

    /**
     * Verifica se dados do administrador foram fornecidos
     */
    public function temDadosAdmin(): bool
    {
        return !empty($this->adminName) 
            && !empty($this->adminEmail) 
            && !empty($this->adminPassword);
    }

    /**
     * Converter DTO para array (para serialização em Jobs)
     */
    public function toArray(): array
    {
        return [
            'razao_social' => $this->razaoSocial,
            'nome_fantasia' => $this->nomeFantasia,
            'cnpj' => $this->cnpj,
            'email' => $this->email,
            'status' => $this->status,
            'endereco' => $this->endereco,
            'cidade' => $this->cidade,
            'estado' => $this->estado,
            'cep' => $this->cep,
            'telefones' => $this->telefones,
            'emails_adicionais' => $this->emailsAdicionais,
            'telefone_fixo' => $this->telefoneFixo,
            'email_financeiro' => $this->emailFinanceiro,
            'email_licitacao' => $this->emailLicitacao,
            'banco' => $this->banco,
            'agencia' => $this->agencia,
            'conta' => $this->conta,
            'tipo_conta' => $this->tipoConta,
            'pix' => $this->pix,
            'favorecido_razao_social' => $this->favorecidoRazaoSocial,
            'favorecido_cnpj' => $this->favorecidoCnpj,
            'representante_legal_nome' => $this->representanteLegalNome,
            'representante_legal_cpf' => $this->representanteLegalCpf,
            'representante_legal_cargo' => $this->representanteLegalCargo,
            'representante_legal_rg' => $this->representanteLegalRg,
            'representante_legal_telefone' => $this->representanteLegalTelefone,
            'representante_legal_email' => $this->representanteLegalEmail,
            'inscricao_estadual' => $this->inscricaoEstadual,
            'inscricao_municipal' => $this->inscricaoMunicipal,
            'cnae_principal' => $this->cnaePrincipal,
            'data_abertura' => $this->dataAbertura,
            'responsavel_comercial' => $this->responsavelComercial,
            'responsavel_financeiro' => $this->responsavelFinanceiro,
            'responsavel_licitacoes' => $this->responsavelLicitacoes,
            'ramo_atuacao' => $this->ramoAtuacao,
            'principais_produtos_servicos' => $this->principaisProdutosServicos,
            'marcas_trabalhadas' => $this->marcasTrabalhadas,
            'observacoes' => $this->observacoes,
            'site' => $this->site,
            'logo' => $this->logo,
            'admin_name' => $this->adminName,
            'admin_email' => $this->adminEmail,
            'admin_password' => $this->adminPassword,
        ];
    }
}




