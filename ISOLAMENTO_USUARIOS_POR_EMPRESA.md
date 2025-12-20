# 🔒 Isolamento de Usuários por Empresa (Tenant)

## ✅ Como Funciona

Cada empresa (tenant) tem seu **próprio banco de dados isolado**. Isso significa:

- ✅ **Cada tenant tem seus próprios usuários**
- ✅ **Um email pode existir em múltiplos tenants** (empresas diferentes)
- ✅ **Dentro do mesmo tenant, o email é único**
- ✅ **Usuários de uma empresa NÃO veem usuários de outras empresas**

## 🔧 Implementação

### 1. **Estrutura de Dados**

- Cada tenant tem seu próprio banco de dados
- Tabela `users` existe em cada banco do tenant
- Não há compartilhamento de dados entre tenants

### 2. **Validação de Email**

A validação `unique:users,email` funciona **apenas dentro do banco do tenant atual**:

```php
// No AdminUserController::store()
'email' => [
    'required',
    'email',
    'max:255',
    Rule::unique('users', 'email')->whereNull('deleted_at'),
],

// No AdminUserController::update()
'email' => [
    'sometimes',
    'required',
    'email',
    'max:255',
    Rule::unique('users', 'email')
        ->ignore($user->id)
        ->whereNull('deleted_at'),
],
```

### 3. **Contexto do Tenant**

Todas as operações de usuário são feitas **dentro do contexto do tenant**:

```php
// Inicializar tenant
tenancy()->initialize($tenant);

// Operações com User (dentro do banco do tenant)
$user = User::create([...]);

// Finalizar tenant
tenancy()->end();
```

## 📋 Exemplo Prático

### Cenário 1: Mesmo email em empresas diferentes ✅ PERMITIDO

- **Empresa A (tenant-a):** usuário `joao@exemplo.com`
- **Empresa B (tenant-b):** usuário `joao@exemplo.com`

✅ **Isso é permitido** porque cada empresa tem seu próprio banco.

### Cenário 2: Email duplicado na mesma empresa ❌ BLOQUEADO

- **Empresa A (tenant-a):** tentar criar dois usuários com `joao@exemplo.com`

❌ **Isso é bloqueado** pela validação `unique:users,email`.

## 🔍 Verificação

Para verificar se está funcionando corretamente:

1. **Criar usuário na Empresa A:**
   - Email: `teste@exemplo.com`
   - Deve funcionar ✅

2. **Criar usuário na Empresa B:**
   - Email: `teste@exemplo.com` (mesmo email)
   - Deve funcionar ✅ (empresas diferentes)

3. **Tentar criar segundo usuário na Empresa A:**
   - Email: `teste@exemplo.com` (mesmo email, mesma empresa)
   - Deve dar erro ❌ (email já existe nesta empresa)

## 🎯 Resultado

- ✅ Cada empresa tem seus próprios usuários
- ✅ Emails podem repetir entre empresas diferentes
- ✅ Emails são únicos dentro da mesma empresa
- ✅ Isolamento completo de dados
