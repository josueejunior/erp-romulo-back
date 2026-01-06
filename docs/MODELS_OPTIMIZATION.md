# Otimização dos Modelos

## Melhorias Implementadas

### 1. BaseModel com Funcionalidades Comuns

**Antes:**
```php
class MeuModel extends Model
{
    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    const DELETED_AT = Blueprint::DELETED_AT;
}
```

**Agora:**
```php
class MeuModel extends BaseModel
{
    // Constantes já definidas na classe base
    // Métodos úteis disponíveis:
    // - getTableName(): string
    // - getPrimaryKeyName(): string
    // - scopeActive($query)
    // - isActive(): bool
    // - activate(): bool
    // - deactivate(): bool
}
```

### 2. Trait BelongsToEmpresaTrait

Elimina repetição do relacionamento `empresa()`:

**Antes:**
```php
class Contrato extends BaseModel
{
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
```

**Agora:**
```php
class Contrato extends BaseModel
{
    use BelongsToEmpresaTrait;
    // empresa() já disponível automaticamente
}
```

### 3. Trait HasSoftDeletesWithEmpresa

Combina `SoftDeletes` + `HasEmpresaScope` em um único trait:

**Antes:**
```php
class Orgao extends BaseModel
{
    use SoftDeletes, HasEmpresaScope;
}
```

**Agora:**
```php
class Orgao extends BaseModel
{
    use HasSoftDeletesWithEmpresa;
    // SoftDeletes + HasEmpresaScope em um único trait
}
```

## Benefícios

### ✅ Redução de Código Duplicado
- **Antes:** ~15 linhas por modelo (constantes + relacionamento empresa)
- **Agora:** 1 linha (trait)

### ✅ Manutenibilidade
- Mudanças centralizadas em traits
- Fácil adicionar funcionalidades comuns

### ✅ Consistência
- Todos os modelos seguem o mesmo padrão
- Menos erros de digitação

### ✅ Funcionalidades Úteis no BaseModel
- Métodos para trabalhar com campo `ativo`
- Scopes comuns
- Helpers para tabela e chave primária

## Padrão Recomendado

### Modelo com SoftDeletes e Empresa:
```php
class MeuModel extends BaseModel
{
    use HasSoftDeletesWithEmpresa, BelongsToEmpresaTrait;
    
    protected $fillable = ['empresa_id', 'nome', 'ativo'];
}
```

### Modelo apenas com Empresa (sem SoftDeletes):
```php
class MeuModel extends BaseModel
{
    use HasEmpresaScope, BelongsToEmpresaTrait;
    
    protected $fillable = ['empresa_id', 'nome'];
}
```

### Modelo com Timestamps Customizados:
```php
class MeuModel extends BaseModel
{
    use HasSoftDeletesWithEmpresa, HasTimestampsCustomizados, BelongsToEmpresaTrait;
    
    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'campo' => 'array',
        ]);
    }
}
```

## Modelos Atualizados

✅ Orgao  
✅ Setor  
✅ Contrato  
✅ Empenho  
✅ NotaFiscal  
✅ AutorizacaoFornecimento  
✅ CustoIndireto  
✅ DocumentoHabilitacao  
✅ Orcamento  

## Próximos Passos

1. Aplicar padrão em modelos restantes
2. Criar traits para padrões comuns (ex: relacionamento com Processo)
3. Adicionar mais métodos úteis ao BaseModel conforme necessário





