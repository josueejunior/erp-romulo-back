# 📋 Cadastro de Tenant com Usuário Administrador

## ✅ Implementação Completa

Foi implementado um fluxo integrado para cadastrar novos tenants (empresas) junto com seus respectivos usuários administradores em uma única transação.

---

## 🔄 Fluxo de Cadastro

### 1. **Endpoint Público** (`/api/v1/tenants` - POST)
Cadastro completo de tenant + administrador (obrigatório)

### 2. **Endpoint Admin** (`/admin/empresas` - POST)
Cadastro de tenant com administrador opcional (para uso no painel admin)

---

## 📝 Estrutura da Requisição

### Dados da Empresa (Tenant)
```json
{
  "razao_social": "Empresa Exemplo LTDA",
  "cnpj": "12.345.678/0001-90",
  "email": "contato@exemplo.com",
  "status": "ativa",
  "endereco": "Rua Exemplo, 123",
  "cidade": "São Paulo",
  "estado": "SP",
  "cep": "01234-567",
  "telefones": ["(11) 1234-5678"],
  "emails_adicionais": ["vendas@exemplo.com"],
  "banco": "Banco do Brasil",
  "agencia": "1234-5",
  "conta": "12345-6",
  "tipo_conta": "corrente",
  "pix": "contato@exemplo.com",
  "representante_legal_nome": "João Silva",
  "representante_legal_cpf": "123.456.789-00",
  "representante_legal_cargo": "Diretor",
  "logo": "https://exemplo.com/logo.png"
}
```

### Dados do Administrador
```json
{
  "admin_name": "João Silva",
  "admin_email": "admin@exemplo.com",
  "admin_password": "SenhaForte123!"
}
```

### Requisição Completa
```json
{
  "razao_social": "Empresa Exemplo LTDA",
  "cnpj": "12.345.678/0001-90",
  "email": "contato@exemplo.com",
  "admin_name": "João Silva",
  "admin_email": "admin@exemplo.com",
  "admin_password": "SenhaForte123!"
}
```

---

## 🔒 Validação de Senha

### Requisitos da Senha Forte
A senha deve atender **TODOS** os seguintes critérios:

- ✅ **Mínimo 8 caracteres**
- ✅ **Pelo menos uma letra maiúscula** (A-Z)
- ✅ **Pelo menos uma letra minúscula** (a-z)
- ✅ **Pelo menos um número** (0-9)
- ✅ **Pelo menos um caractere especial** (@$!%*?&)

### Regex de Validação
```regex
^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$
```

### Exemplos de Senhas Válidas
- ✅ `SenhaForte123!`
- ✅ `MinhaSenha@2024`
- ✅ `Admin123$`
- ✅ `Teste@123`

### Exemplos de Senhas Inválidas
- ❌ `senhafraca` (sem maiúscula, número e especial)
- ❌ `SENHAFRACA` (sem minúscula, número e especial)
- ❌ `Senha123` (sem caractere especial)
- ❌ `Senha@` (sem número)
- ❌ `Senha1!` (menos de 8 caracteres)

---

## 📤 Respostas da API

### ✅ Sucesso (201 Created)
```json
{
  "message": "Empresa e usuário administrador criados com sucesso!",
  "success": true,
  "data": {
    "tenant": {
      "id": "empresa-exemplo",
      "razao_social": "Empresa Exemplo LTDA",
      "cnpj": "12.345.678/0001-90",
      "email": "contato@exemplo.com",
      "status": "ativa"
    },
    "admin_user": {
      "name": "João Silva",
      "email": "admin@exemplo.com"
    }
  }
}
```

### ❌ Erro de Validação (422 Unprocessable Entity)
```json
{
  "message": "Dados inválidos. Verifique os campos preenchidos.",
  "errors": {
    "razao_social": ["A razão social da empresa é obrigatória."],
    "admin_email": ["O e-mail do administrador deve ser válido."],
    "admin_password": ["A senha deve ter no mínimo 8 caracteres, incluindo pelo menos uma letra maiúscula, uma minúscula, um número e um caractere especial."]
  },
  "success": false
}
```

### ❌ Erro de Servidor (500 Internal Server Error)
```json
{
  "message": "Erro ao processar a solicitação. Por favor, tente novamente.",
  "error": "Detalhes do erro (apenas em modo debug)",
  "success": false
}
```

---

## 🔄 Processo Interno

Quando um tenant é criado, o sistema executa automaticamente:

1. ✅ **Criação do Tenant** no banco central
2. ✅ **Criação do Banco de Dados** do tenant
3. ✅ **Execução das Migrations** do tenant
4. ✅ **Criação das Roles e Permissões** (Administrador, Operacional, Financeiro, Consulta)
5. ✅ **Criação da Empresa** dentro do tenant
6. ✅ **Criação do Usuário Administrador**
7. ✅ **Associação do Usuário à Empresa**
8. ✅ **Atribuição da Role de Administrador**

Tudo isso acontece em uma **transação única**, garantindo integridade dos dados.

---

## 🛡️ Segurança

### Hash de Senha
- ✅ Senhas são **hasheadas** usando `bcrypt` antes de serem salvas
- ✅ **Nunca** são armazenadas em texto plano
- ✅ Usa `Hash::make()` do Laravel

### Validação em Duas Camadas
1. **Frontend**: Validação em tempo real enquanto o usuário digita
2. **Backend**: Validação obrigatória antes de salvar no banco

### Transações
- ✅ Todas as operações são executadas dentro de uma transação
- ✅ Em caso de erro, todas as mudanças são revertidas (rollback)
- ✅ Garante consistência dos dados

---

## 📱 Exemplo de Uso (JavaScript/Fetch)

```javascript
async function cadastrarTenant(dados) {
  try {
    const response = await fetch('/api/v1/tenants', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        razao_social: dados.razao_social,
        cnpj: dados.cnpj,
        email: dados.email,
        admin_name: dados.admin_name,
        admin_email: dados.admin_email,
        admin_password: dados.admin_password,
      }),
    });

    const result = await response.json();

    if (response.ok && result.success) {
      // Sucesso
      console.log('✅ Empresa criada:', result.data.tenant);
      console.log('✅ Admin criado:', result.data.admin_user);
      alert('Empresa e usuário administrador criados com sucesso!');
    } else {
      // Erro de validação
      console.error('❌ Erros:', result.errors);
      alert('Erro ao cadastrar: ' + result.message);
    }
  } catch (error) {
    // Erro de rede
    console.error('❌ Erro:', error);
    alert('Erro ao conectar com o servidor');
  }
}
```

---

## 🎨 Exemplo de Validação Frontend (React)

```jsx
import { useState } from 'react';

function PasswordStrength({ password }) {
  const checks = {
    minLength: password.length >= 8,
    hasUpperCase: /[A-Z]/.test(password),
    hasLowerCase: /[a-z]/.test(password),
    hasNumber: /[0-9]/.test(password),
    hasSpecial: /[@$!%*?&]/.test(password),
  };

  const allValid = Object.values(checks).every(Boolean);

  return (
    <div className="password-strength">
      <div className={checks.minLength ? 'valid' : 'invalid'}>
        {checks.minLength ? '✅' : '❌'} Mínimo 8 caracteres
      </div>
      <div className={checks.hasUpperCase ? 'valid' : 'invalid'}>
        {checks.hasUpperCase ? '✅' : '❌'} Letra maiúscula
      </div>
      <div className={checks.hasLowerCase ? 'valid' : 'invalid'}>
        {checks.hasLowerCase ? '✅' : '❌'} Letra minúscula
      </div>
      <div className={checks.hasNumber ? 'valid' : 'invalid'}>
        {checks.hasNumber ? '✅' : '❌'} Número
      </div>
      <div className={checks.hasSpecial ? 'valid' : 'invalid'}>
        {checks.hasSpecial ? '✅' : '❌'} Caractere especial (@$!%*?&)
      </div>
      {allValid && <div className="success">✅ Senha forte!</div>}
    </div>
  );
}
```

---

## 📚 Arquivos Modificados

1. ✅ `app/Http/Controllers/Api/TenantController.php`
   - Método `store()` atualizado para criar tenant + admin
   - Validação de senha forte
   - Mensagens de sucesso/erro melhoradas

2. ✅ `app/Http/Controllers/Admin/AdminTenantController.php`
   - Método `store()` atualizado (admin opcional)
   - Mesma funcionalidade do endpoint público

3. ✅ `app/Rules/StrongPassword.php`
   - Já existia e está funcionando corretamente

---

## ✅ Checklist de Implementação

- [x] Validação de senha forte (backend)
- [x] Criação automática de tenant
- [x] Criação automática de banco de dados
- [x] Criação automática de empresa
- [x] Criação automática de usuário administrador
- [x] Associação usuário-empresa
- [x] Atribuição de role de Administrador
- [x] Mensagens de sucesso claras
- [x] Mensagens de erro detalhadas
- [x] Transações para garantir integridade
- [x] Hash de senha seguro
- [x] Logs de erro estruturados

---

## 🚀 Próximos Passos (Frontend)

Para completar a implementação, você precisa:

1. ✅ Criar formulário de cadastro de tenant
2. ✅ Adicionar validação de senha em tempo real
3. ✅ Exibir indicadores de força de senha
4. ✅ Mostrar mensagens de sucesso/erro
5. ✅ Tratar erros de validação específicos

---

## 📞 Suporte

Em caso de dúvidas ou problemas:
- Verifique os logs em `storage/logs/laravel.log`
- Verifique se o Redis está funcionando (para cache)
- Verifique se o banco de dados está acessível
- Verifique as permissões do usuário do banco de dados

