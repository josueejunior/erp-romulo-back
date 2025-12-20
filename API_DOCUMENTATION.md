# Documentação da API - ERP Licitações

## Autenticação

A API usa Laravel Sanctum para autenticação via tokens Bearer.

### Endpoints Públicos

#### Criar Tenant (Empresa)
```
POST /api/tenants
Content-Type: application/json

{
  "razao_social": "Empresa Exemplo LTDA",
  "cnpj": "12.345.678/0001-90",
  "email": "contato@exemplo.com",
  "status": "ativa"
}
```

#### Login
```
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@exemplo.com",
  "password": "password",
  "tenant_id": "uuid-do-tenant"
}

Response:
{
  "user": {
    "id": 1,
    "name": "Administrador",
    "email": "admin@exemplo.com"
  },
  "tenant": {
    "id": "uuid",
    "razao_social": "Empresa Exemplo LTDA",
    "cnpj": "12.345.678/0001-90"
  },
  "token": "1|token...",
  "message": "Use o header X-Tenant-ID nas próximas requisições..."
}
```

#### Registrar Usuário
```
POST /api/auth/register
Content-Type: application/json

{
  "name": "Nome do Usuário",
  "email": "usuario@exemplo.com",
  "password": "senha123",
  "password_confirmation": "senha123",
  "tenant_id": "uuid-do-tenant"
}
```

### Endpoints Autenticados

Todas as requisições autenticadas precisam de:
- Header: `Authorization: Bearer {token}`
- Header: `X-Tenant-ID: {tenant_id}`

#### Obter Usuário Atual
```
GET /api/auth/user
Headers:
  Authorization: Bearer {token}
  X-Tenant-ID: {tenant_id}
```

#### Logout
```
POST /api/auth/logout
Headers:
  Authorization: Bearer {token}
  X-Tenant-ID: {tenant_id}
```

## Endpoints Principais

### Dashboard
```
GET /api/dashboard
```

### Processos
```
GET    /api/processos
POST   /api/processos
GET    /api/processos/{id}
PUT    /api/processos/{id}
DELETE /api/processos/{id}
POST   /api/processos/{id}/marcar-vencido
POST   /api/processos/{id}/marcar-perdido
```

### Itens do Processo
```
GET    /api/processos/{processo_id}/itens
POST   /api/processos/{processo_id}/itens
GET    /api/processos/{processo_id}/itens/{item_id}
PUT    /api/processos/{processo_id}/itens/{item_id}
DELETE /api/processos/{processo_id}/itens/{item_id}
```

### Orçamentos
```
GET    /api/processos/{processo_id}/itens/{item_id}/orcamentos
POST   /api/processos/{processo_id}/itens/{item_id}/orcamentos
GET    /api/processos/{processo_id}/itens/{item_id}/orcamentos/{orcamento_id}
PUT    /api/processos/{processo_id}/itens/{item_id}/orcamentos/{orcamento_id}
DELETE /api/processos/{processo_id}/itens/{item_id}/orcamentos/{orcamento_id}
```

### Formação de Preços
```
GET    /api/processos/{processo_id}/itens/{item_id}/orcamentos/{orcamento_id}/formacao-preco
POST   /api/processos/{processo_id}/itens/{item_id}/orcamentos/{orcamento_id}/formacao-preco
PUT    /api/processos/{processo_id}/itens/{item_id}/orcamentos/{orcamento_id}/formacao-preco/{formacao_preco_id}
```

### Disputa
```
GET /api/processos/{processo_id}/disputa
PUT /api/processos/{processo_id}/disputa
Body: {
  "itens": [
    {
      "id": 1,
      "valor_final_sessao": 1000.00,
      "classificacao": 1
    }
  ]
}
```

### Julgamento
```
GET /api/processos/{processo_id}/julgamento
PUT /api/processos/{processo_id}/julgamento
Body: {
  "itens": [
    {
      "id": 1,
      "status_item": "aceito",
      "valor_negociado": 950.00,
      "chance_arremate": "alta",
      "chance_percentual": 80
    }
  ]
}
```

### Contratos
```
GET    /api/processos/{processo_id}/contratos
POST   /api/processos/{processo_id}/contratos
GET    /api/processos/{processo_id}/contratos/{contrato_id}
PUT    /api/processos/{processo_id}/contratos/{contrato_id}
DELETE /api/processos/{processo_id}/contratos/{contrato_id}
```

### Autorizações de Fornecimento (AF)
```
GET    /api/processos/{processo_id}/autorizacoes-fornecimento
POST   /api/processos/{processo_id}/autorizacoes-fornecimento
GET    /api/processos/{processo_id}/autorizacoes-fornecimento/{af_id}
PUT    /api/processos/{processo_id}/autorizacoes-fornecimento/{af_id}
DELETE /api/processos/{processo_id}/autorizacoes-fornecimento/{af_id}
```

### Empenhos
```
GET    /api/processos/{processo_id}/empenhos
POST   /api/processos/{processo_id}/empenhos
GET    /api/processos/{processo_id}/empenhos/{empenho_id}
PUT    /api/processos/{processo_id}/empenhos/{empenho_id}
DELETE /api/processos/{processo_id}/empenhos/{empenho_id}
```

### Notas Fiscais
```
GET    /api/processos/{processo_id}/notas-fiscais
POST   /api/processos/{processo_id}/notas-fiscais
GET    /api/processos/{processo_id}/notas-fiscais/{nota_fiscal_id}
PUT    /api/processos/{processo_id}/notas-fiscais/{nota_fiscal_id}
DELETE /api/processos/{processo_id}/notas-fiscais/{nota_fiscal_id}
```

### Cadastros

#### Órgãos
```
GET    /api/orgaos
POST   /api/orgaos
GET    /api/orgaos/{id}
PUT    /api/orgaos/{id}
DELETE /api/orgaos/{id}
```

#### Fornecedores
```
GET    /api/fornecedores
POST   /api/fornecedores
GET    /api/fornecedores/{id}
PUT    /api/fornecedores/{id}
DELETE /api/fornecedores/{id}
```

#### Documentos de Habilitação
```
GET    /api/documentos-habilitacao
POST   /api/documentos-habilitacao
GET    /api/documentos-habilitacao/{id}
PUT    /api/documentos-habilitacao/{id}
DELETE /api/documentos-habilitacao/{id}
```

### Relatórios

#### Relatório Financeiro
```
GET /api/relatorios/financeiro?data_inicio=2025-01-01&data_fim=2025-12-31
```

## Estrutura Multi-Tenancy

Cada tenant (empresa) possui seu próprio banco de dados PostgreSQL separado. O sistema usa o pacote `stancl/tenancy` para gerenciar isso automaticamente.

### Como Funciona

1. **Banco Central**: Armazena apenas os tenants (empresas) e domínios
2. **Bancos de Tenant**: Cada tenant tem seu próprio banco de dados (`tenant_{uuid}`)
3. **Isolamento**: Dados de um tenant nunca são acessíveis por outro tenant

### Identificação do Tenant

O tenant é identificado através do header `X-Tenant-ID` em todas as requisições autenticadas.

## Configuração do Frontend React

### Exemplo de uso com Axios

```javascript
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
});

// Interceptor para adicionar token e tenant_id
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  const tenantId = localStorage.getItem('tenant_id');
  
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  
  if (tenantId) {
    config.headers['X-Tenant-ID'] = tenantId;
  }
  
  return config;
});

// Login
const login = async (email, password, tenantId) => {
  const response = await api.post('/auth/login', {
    email,
    password,
    tenant_id: tenantId,
  });
  
  localStorage.setItem('token', response.data.token);
  localStorage.setItem('tenant_id', response.data.tenant.id);
  localStorage.setItem('user', JSON.stringify(response.data.user));
  
  return response.data;
};
```

## PostgreSQL

O sistema usa PostgreSQL como banco de dados. Certifique-se de ter o PostgreSQL instalado e configurado no `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=erp_licitacoes_central
DB_USERNAME=postgres
DB_PASSWORD=sua_senha
```

## CORS

Para desenvolvimento React, configure o CORS no `.env`:

```env
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
```






