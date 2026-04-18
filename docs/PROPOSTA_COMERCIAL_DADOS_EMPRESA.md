# Dados da Empresa para Proposta Comercial

## Resumo das Alterações

Sistema agora busca automaticamente os dados cadastrados da empresa (tenant) para gerar as propostas comerciais.

## Novos Campos Adicionados

### Migration: `2025_12_31_000001_add_nome_fantasia_cargo_representante_to_empresas_table.php`

**Campos adicionados à tabela `empresas`:**
- `nome_fantasia` (string, 255) - Nome fantasia da empresa (opcional)
- `cargo_representante` (string, 100) - Cargo do representante legal (opcional)

### Campos já existentes utilizados:
- `razao_social` - Razão social da empresa
- `cnpj` - CNPJ da empresa
- `cep`, `logradouro`, `numero`, `bairro`, `complemento`, `cidade`, `estado` - Endereço completo
- `email` - E-mail da empresa
- `telefone` - Telefone principal
- `banco_nome`, `banco_agencia`, `banco_conta` - Dados bancários
- `representante_legal` - Nome do representante legal (campo: `representante_legal_nome` no tenant)
- `logo` - Logo da empresa (base64, URL ou caminho de arquivo)

## Como Funciona

### 1. Cadastro dos Dados

Os dados da empresa devem ser cadastrados através do **Controller Admin** (`AdminTenantController`):

**Endpoint:** `PUT /api/admin/empresas/{tenant}`

**Exemplo de payload:**
```json
{
  "razao_social": "Empresa Exemplo LTDA",
  "nome_fantasia": "Empresa Exemplo",
  "cnpj": "12.345.678/0001-90",
  "endereco": "Rua Exemplo, 123",
  "cidade": "São Paulo",
  "estado": "SP",
  "cep": "01234-567",
  "email": "contato@empresa.com",
  "telefone": "(11) 98765-4321",
  "banco_nome": "Banco do Brasil",
  "banco_agencia": "1234-5",
  "banco_conta": "12345-6",
  "representante_legal_nome": "João da Silva",
  "cargo_representante": "Diretor Comercial",
  "logo": "logos/empresa-logo.png"
}
```

### 2. Geração Automática da Proposta

Quando o usuário solicita a exportação da proposta comercial de um processo:

**Endpoint:** `GET /api/processos/{processo}/exportar/proposta-comercial`

O sistema automaticamente:
1. Identifica o **tenant** (empresa) do usuário autenticado
2. Busca os dados cadastrados da empresa
3. Preenche o template da proposta com esses dados
4. Gera o PDF ou HTML com as informações corretas

### 3. Dados Utilizados no Template

O serviço `ExportacaoService::gerarPropostaComercial()` busca e disponibiliza:

```php
$dados = [
    'nome_empresa' => $tenant->razao_social,
    'nome_fantasia' => $tenant->nome_fantasia ?? $tenant->razao_social,
    'cnpj_empresa' => $tenant->cnpj,
    'endereco_completo' => "{$endereco}, {$cidade}, {$estado}",
    'email_empresa' => $tenant->email,
    'telefone_empresa' => $tenant->telefones[0],
    'banco_empresa' => $tenant->banco,
    'agencia_empresa' => $tenant->agencia,
    'conta_empresa' => $tenant->conta,
    'representante_legal' => $tenant->representante_legal_nome,
    'cargo_representante' => $tenant->cargo_representante,
    'logo_base64' => /* logo convertida para base64 */,
    // ... outros dados do processo
];
```

### 4. Template Atualizado

O template `proposta_comercial.blade.php` foi atualizado para exibir:

**Na seção de dados da empresa:**
```blade
Representante legal na assinatura do Contrato: {{ $representante_legal ?: 'N/A' }}
@if($cargo_representante) - {{ $cargo_representante }}@endif
```

**Na seção de assinatura:**
```blade
{{ $representante_legal ?: 'N/A' }}
{{ $cargo_representante ?: 'CARGO' }}
{{ $nome_empresa }} ({{ $cnpj_empresa ?: 'N/A' }})
```

## Vantagens

✅ **Centralizado**: Dados da empresa cadastrados uma única vez  
✅ **Automático**: Não precisa preencher manualmente a cada proposta  
✅ **Consistente**: Todas as propostas usam os mesmos dados cadastrados  
✅ **Personalizável**: Cada empresa (tenant) tem seus próprios dados  
✅ **Flexível**: Campos opcionais têm valores padrão ("N/A", "CARGO")

## Campos Opcionais

Se algum campo não estiver preenchido, o sistema usa valores padrão:

| Campo | Valor Padrão |
|-------|--------------|
| `nome_fantasia` | Usa `razao_social` |
| `cargo_representante` | "CARGO" |
| `representante_legal` | "N/A" |
| `email`, `telefone`, etc. | "N/A" |
| `logo` | "INSIRA SUA LOGO AQUI!!!!" |

## Migração (Executar)

Para aplicar as alterações no banco de dados:

```bash
php artisan migrate
```

Isso criará os campos `nome_fantasia` e `cargo_representante` na tabela `empresas`.

## Próximos Passos

1. **Frontend**: Criar/atualizar formulário de cadastro de empresa para incluir os novos campos
2. **Validações**: Adicionar validações específicas se necessário (ex: cargo com máximo 100 caracteres)
3. **Testes**: Testar geração de propostas com diferentes combinações de dados preenchidos/vazios
4. **Documentação de usuário**: Criar guia para o usuário final sobre como preencher os dados da empresa
