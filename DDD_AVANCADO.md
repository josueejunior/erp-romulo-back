# 🚀 DDD Avançado - Value Objects, Domain Services e Events

## ✅ Implementação Completa

### 📦 Value Objects Criados

#### 1. **Email** (`Domain/Shared/ValueObjects/Email.php`)
- ✅ Valida formato de email
- ✅ Normaliza (lowercase, trim)
- ✅ Imutável (readonly)
- ✅ Método `equals()` para comparação

**Uso:**
```php
$email = new Email('user@example.com');
// Valida automaticamente
// Se inválido, lança DomainException
```

#### 2. **Senha** (`Domain/Shared/ValueObjects/Senha.php`)
- ✅ Valida força da senha (8+ chars, maiúscula, minúscula, número, especial)
- ✅ Faz hash automaticamente
- ✅ Método `verificar()` para checar senha
- ✅ Nunca armazena senha em texto plano

**Uso:**
```php
$senha = Senha::fromPlainText('SenhaForte123!');
// Valida força e faz hash
// $senha->hash contém o hash
```

#### 3. **CNPJ** (`Domain/Shared/ValueObjects/Cnpj.php`)
- ✅ Valida formato (14 dígitos)
- ✅ Valida dígitos verificadores
- ✅ Normaliza (apenas números)
- ✅ Método `formatado()` para exibição

**Uso:**
```php
$cnpj = new Cnpj('12.345.678/0001-90');
// Valida automaticamente
// $cnpj->formatado() retorna '12.345.678/0001-90'
```

#### 4. **CPF** (`Domain/Shared/ValueObjects/Cpf.php`)
- ✅ Valida formato (11 dígitos)
- ✅ Valida dígitos verificadores
- ✅ Normaliza (apenas números)
- ✅ Método `formatado()` para exibição

#### 5. **Money** (`Domain/Shared/ValueObjects/Money.php`)
- ✅ Armazena em centavos (int) - evita problemas de precisão
- ✅ Métodos: `adicionar()`, `subtrair()`, `multiplicar()`
- ✅ Métodos de comparação: `maiorQue()`, `menorQue()`, `igual()`
- ✅ Método `formatado()` para exibição (R$ X.XXX,XX)

**Uso:**
```php
$valor1 = Money::fromReais(100.50); // R$ 100,50
$valor2 = Money::fromReais(50.25);  // R$ 50,25
$total = $valor1->adicionar($valor2); // R$ 150,75
```

#### 6. **Status** (`Domain/Shared/ValueObjects/Status.php`)
- ✅ Valida valores permitidos (ativa, inativa, pendente, cancelada)
- ✅ Métodos helper: `isAtiva()`, `isInativa()`
- ✅ Constantes para valores válidos

**Uso:**
```php
$status = new Status(Status::ATIVA);
if ($status->isAtiva()) {
    // ...
}
```

---

### 🔧 Domain Services Criados

#### 1. **UserRoleService** (`Domain/Auth/Services/UserRoleServiceInterface.php`)
- ✅ `atribuirRole()` - Atribuir role a usuário
- ✅ `removerRole()` - Remover role
- ✅ `sincronizarRoles()` - Sincronizar múltiplas roles
- ✅ `temRole()` - Verificar se tem role

**Implementação:** `Infrastructure/Auth/UserRoleService.php`
- Usa Spatie Permission (detalhe de infraestrutura)
- Domínio não conhece Spatie

**Uso no Use Case:**
```php
$this->roleService->atribuirRole($user, 'Administrador');
```

---

### 📡 Domain Events Criados

#### 1. **DomainEvent Interface** (`Domain/Shared/Events/DomainEvent.php`)
- ✅ Interface base para todos os eventos
- ✅ Métodos: `ocorreuEm()`, `agregadoId()`

#### 2. **UsuarioCriado** (`Domain/Auth/Events/UsuarioCriado.php`)
- ✅ Disparado quando usuário é criado
- ✅ Contém: userId, email, nome, tenantId, empresaId

#### 3. **SenhaAlterada** (`Domain/Auth/Events/SenhaAlterada.php`)
- ✅ Disparado quando senha é alterada
- ✅ Contém: userId, email

#### 4. **EmpresaVinculada** (`Domain/Tenant/Events/EmpresaVinculada.php`)
- ✅ Disparado quando empresa é vinculada a usuário
- ✅ Contém: userId, empresaId, tenantId, perfil

#### 5. **EventDispatcherInterface** (`Domain/Shared/Events/EventDispatcherInterface.php`)
- ✅ Interface para disparar eventos
- ✅ Domínio não conhece Laravel Events

**Implementação:** `Infrastructure/Events/LaravelEventDispatcher.php`
- Usa Laravel Events (detalhe de infraestrutura)

---

### 🎧 Listeners Criados

#### 1. **UsuarioCriadoListener**
- ✅ Log de auditoria
- ✅ Envio de e-mail (comentado, pode ser feito em queue)
- ✅ Notificações, webhooks, etc.

#### 2. **SenhaAlteradaListener**
- ✅ Log de segurança
- ✅ E-mail de notificação
- ✅ Pode invalidar tokens antigos

#### 3. **EmpresaVinculadaListener**
- ✅ Log de auditoria
- ✅ Atualização de cache

---

### 🔄 Exemplo de Uso Completo

#### Use Case Atualizado (CriarUsuarioUseCase):

```php
public function executar(CriarUsuarioDTO $dto): User
{
    // 1. Value Object para Email
    $email = new Email($dto->email);
    
    // 2. Value Object para Senha (valida e faz hash)
    $senha = Senha::fromPlainText($dto->senha);
    
    // 3. Criar entidade
    $user = new User(
        id: null,
        tenantId: $dto->tenantId,
        nome: $dto->nome,
        email: $email->value,
        senhaHash: $senha->hash,
        empresaAtivaId: $dto->empresaId,
    );
    
    // 4. Persistir
    $user = $this->userRepository->criar($user, $dto->empresaId, $dto->role);
    
    // 5. Domain Service para roles
    $this->roleService->atribuirRole($user, $dto->role);
    
    // 6. Disparar Domain Event
    $this->eventDispatcher->dispatch(
        new UsuarioCriado(
            userId: $user->id,
            email: $user->email,
            nome: $user->nome,
            tenantId: $user->tenantId,
            empresaId: $user->empresaAtivaId,
        )
    );
    
    return $user;
}
```

---

### 📊 Benefícios

#### ✅ **Value Objects**
- **Consistência**: Validação centralizada
- **Segurança**: Nunca aceita dados inválidos
- **Reutilização**: Mesma validação em todo lugar
- **Testabilidade**: Fácil testar isoladamente

#### ✅ **Domain Services**
- **Separação**: Lógica complexa fora das entidades
- **Reutilização**: Mesmo serviço em múltiplos Use Cases
- **Testabilidade**: Mock fácil

#### ✅ **Domain Events**
- **Desacoplamento**: Ações secundárias não bloqueiam fluxo principal
- **Escalabilidade**: Fácil adicionar novos listeners
- **Auditoria**: Logs automáticos
- **Flexibilidade**: Pode usar queues, webhooks, etc.

---

### 🎯 Próximos Passos (Opcional)

1. **Mais Value Objects:**
   - CEP
   - Telefone
   - URL
   - Data/Hora customizada

2. **Mais Domain Services:**
   - Calculadora de Impostos
   - Validador de Regras de Negócio
   - Gerador de Códigos

3. **Mais Events:**
   - ProcessoCriado
   - ContratoAssinado
   - PagamentoRealizado

4. **Event Sourcing (Avançado):**
   - Armazenar todos os eventos
   - Reconstruir estado a partir dos eventos

---

## 🏆 Resultado Final

**Sistema agora está em nível EXPERT de DDD:**
- ✅ Value Objects garantem consistência
- ✅ Domain Services para lógica complexa
- ✅ Domain Events para desacoplamento
- ✅ Tudo testável e reutilizável
- ✅ Fácil escalar e manter

