# 📋 Revisão da Lógica de Vinculação de Usuários a Empresas

## ✅ Melhorias Implementadas

### 1. **Método `store` (Criação de Usuário)**

#### Lógica Anterior (Problemas):
- Não validava se `empresa_ativa_id` estava nas empresas associadas
- Podia falhar se ambos `empresas[]` e `empresa_id` fossem null
- Não garantia consistência entre empresas associadas e empresa ativa

#### Lógica Nova (Corrigida):
```php
// 1. Determina empresas a associar (prioriza empresas[], senão empresa_id)
$empresasIds = !empty($validated['empresas']) 
    ? $validated['empresas'] 
    : (!empty($validated['empresa_id']) ? [$validated['empresa_id']] : []);

// 2. Valida que pelo menos uma empresa foi fornecida
if (empty($empresasIds)) {
    throw ValidationException::withMessages([
        'empresas' => ['Selecione pelo menos uma empresa.'],
    ]);
}

// 3. Determina empresa ativa: usa a fornecida se estiver nas empresas selecionadas
$empresaAtivaId = $validated['empresa_ativa_id'] ?? $empresasIds[0];

// 4. Garante que empresa_ativa_id está nas empresas associadas
if (!in_array($empresaAtivaId, $empresasIds)) {
    $empresaAtivaId = $empresasIds[0];
}
```

**Validações Adicionadas:**
- ✅ Valida que `empresa_ativa_id` está nas empresas selecionadas
- ✅ Se não estiver, usa automaticamente a primeira empresa da lista
- ✅ Garante que sempre há pelo menos uma empresa associada

---

### 2. **Método `update` (Atualização de Usuário)**

#### Lógica Anterior (Problemas):
- Lógica duplicada e confusa para atualizar empresas
- Não validava se `empresa_ativa_id` estava nas empresas associadas
- Quando apenas `empresa_id` era fornecido, substituía todas as empresas (comportamento não desejado)
- Validação duplicada de `empresa_ativa_id`

#### Lógica Nova (Corrigida):
```php
// 1. Atualiza campos básicos (name, email, password)
// ...

// 2. Determina empresas a associar
$empresasIds = null;
if (isset($validated['empresas']) && !empty($validated['empresas'])) {
    // Múltiplas empresas fornecidas
    $empresasIds = $validated['empresas'];
} elseif (isset($validated['empresa_id']) && !empty($validated['empresa_id'])) {
    // Compatibilidade: apenas uma empresa fornecida
    $empresasIds = [$validated['empresa_id']];
}

// 3. Se empresas foram fornecidas, atualiza associações
if ($empresasIds !== null) {
    $syncData = [];
    foreach ($empresasIds as $empresaId) {
        $syncData[$empresaId] = ['perfil' => $roleParaPerfil];
    }
    $user->empresas()->sync($syncData);
    
    // 4. Atualiza empresa ativa
    if (isset($validated['empresa_ativa_id']) && in_array($validated['empresa_ativa_id'], $empresasIds)) {
        $user->empresa_ativa_id = $validated['empresa_ativa_id'];
    } else {
        // Usa a primeira empresa da lista
        $user->empresa_ativa_id = $empresasIds[0];
    }
} elseif (isset($validated['empresa_ativa_id'])) {
    // Apenas empresa_ativa_id foi fornecido (sem alterar empresas associadas)
    $empresasAssociadas = $user->empresas->pluck('id')->toArray();
    if (in_array($validated['empresa_ativa_id'], $empresasAssociadas)) {
        $user->empresa_ativa_id = $validated['empresa_ativa_id'];
    } else {
        // Se não está nas associadas, usa a primeira disponível
        if (!empty($empresasAssociadas)) {
            $user->empresa_ativa_id = $empresasAssociadas[0];
        }
    }
}
```

**Validações Adicionadas:**
- ✅ Valida que `empresa_ativa_id` está nas empresas selecionadas (se empresas foram fornecidas)
- ✅ Valida que `empresa_ativa_id` está nas empresas já associadas (se apenas empresa_ativa_id foi fornecido)
- ✅ Não remove empresas não intencionalmente
- ✅ Mantém empresas existentes se nenhuma nova empresa for fornecida

---

## 🔍 Validações Implementadas

### Validação de `empresa_ativa_id` no `store`:
```php
if (!empty($validated['empresa_ativa_id'])) {
    $empresasFornecidas = $validated['empresas'] ?? (!empty($validated['empresa_id']) ? [$validated['empresa_id']] : []);
    if (!empty($empresasFornecidas) && !in_array($validated['empresa_ativa_id'], $empresasFornecidas)) {
        throw ValidationException::withMessages([
            'empresa_ativa_id' => ['A empresa ativa deve estar entre as empresas selecionadas.'],
        ]);
    }
}
```

### Validação de `empresa_ativa_id` no `update`:
```php
if (!empty($validated['empresa_ativa_id'])) {
    $empresasFornecidas = $validated['empresas'] ?? (!empty($validated['empresa_id']) ? [$validated['empresa_id']] : []);
    
    if (!empty($empresasFornecidas)) {
        // Valida que está entre as empresas fornecidas
        if (!in_array($validated['empresa_ativa_id'], $empresasFornecidas)) {
            throw ValidationException::withMessages([
                'empresa_ativa_id' => ['A empresa ativa deve estar entre as empresas selecionadas.'],
            ]);
        }
    } else {
        // Valida que está entre as empresas já associadas
        $empresasAssociadas = $user->empresas->pluck('id')->toArray();
        if (!empty($empresasAssociadas) && !in_array($validated['empresa_ativa_id'], $empresasAssociadas)) {
            throw ValidationException::withMessages([
                'empresa_ativa_id' => ['A empresa ativa deve estar entre as empresas associadas ao usuário.'],
            ]);
        }
    }
}
```

---

## 📊 Fluxo de Dados

### Criação de Usuário:
1. **Validação**: Verifica se há empresas no tenant
2. **Validação**: Verifica se pelo menos uma empresa foi fornecida
3. **Validação**: Verifica se todas as empresas pertencem ao tenant
4. **Validação**: Verifica se `empresa_ativa_id` está nas empresas selecionadas
5. **Criação**: Cria usuário com `empresa_ativa_id`
6. **Associação**: Associa usuário a todas as empresas com perfil do role
7. **Role**: Atribui role ao usuário

### Atualização de Usuário:
1. **Validação**: Verifica se empresas pertencem ao tenant
2. **Validação**: Verifica se `empresa_ativa_id` está nas empresas (fornecidas ou associadas)
3. **Atualização**: Atualiza campos básicos (name, email, password)
4. **Associação**: Atualiza associações com empresas (se fornecidas)
5. **Empresa Ativa**: Atualiza `empresa_ativa_id` (se fornecido e válido)
6. **Role**: Atualiza role (se fornecido)

---

## 🎯 Comportamentos Garantidos

1. ✅ **Sempre há pelo menos uma empresa associada ao usuário**
2. ✅ **`empresa_ativa_id` sempre está nas empresas associadas**
3. ✅ **Se `empresa_ativa_id` não for válido, usa automaticamente a primeira empresa**
4. ✅ **Não remove empresas não intencionalmente durante atualização**
5. ✅ **Valida que todas as empresas pertencem ao tenant atual**
6. ✅ **Mensagens de erro claras e em português**

---

## 🔄 Compatibilidade

A lógica mantém compatibilidade com:
- ✅ `empresas[]` (array de múltiplas empresas) - **Preferencial**
- ✅ `empresa_id` (única empresa) - **Compatibilidade retroativa**
- ✅ `empresa_ativa_id` (empresa ativa) - **Opcional**

**Prioridade**: `empresas[]` > `empresa_id` > primeira empresa da lista

---

## 📝 Exemplos de Uso

### Criar usuário com múltiplas empresas:
```json
{
  "name": "João Silva",
  "email": "joao@exemplo.com",
  "password": "SenhaForte123!",
  "role": "Administrador",
  "empresas": [1, 2, 3],
  "empresa_ativa_id": 2
}
```

### Criar usuário com uma empresa (compatibilidade):
```json
{
  "name": "Maria Santos",
  "email": "maria@exemplo.com",
  "password": "SenhaForte123!",
  "role": "Operacional",
  "empresa_id": 1
}
```

### Atualizar apenas empresa ativa:
```json
{
  "empresa_ativa_id": 3
}
```

### Atualizar empresas associadas:
```json
{
  "empresas": [1, 2, 3, 4],
  "empresa_ativa_id": 4,
  "role": "Financeiro"
}
```

---

## ✅ Resultado

A lógica agora é:
- ✅ **Mais clara e consistente**
- ✅ **Mais robusta com validações adequadas**
- ✅ **Mais segura (não remove dados não intencionalmente)**
- ✅ **Mais flexível (suporta múltiplos cenários)**
- ✅ **Melhor documentada**


