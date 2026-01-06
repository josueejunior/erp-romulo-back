# Padrão de Timestamps Customizados

## Como o Sistema Evita Declarar Tipos Explicitamente

Este documento explica como o sistema usa constantes tipadas do PHP 8.1+ para evitar declarações de tipo explícitas desnecessárias, mantendo o código limpo e aproveitando a inferência de tipos do PHP.

## 1. Constantes Tipadas na Classe Blueprint

### Na classe `App\Database\Schema\Blueprint`:

```php
class Blueprint extends BaseBlueprint
{
    // Constantes tipadas explicitamente (PHP 8.1+)
    public const string CREATED_AT = 'criado_em';
    public const string UPDATED_AT = 'atualizado_em';
    public const string DELETED_AT = 'excluido_em';
}
```

**Por que declarar tipos aqui?**
- É a fonte da verdade para todo o sistema
- Garante type safety em nível de framework
- Permite que o PHP infira tipos em outros lugares

## 2. Uso nos Modelos (NÃO precisa declarar tipos)

### No Model:

```php
use App\Database\Schema\Blueprint;

class DocumentoHabilitacao extends Model
{
    // ✅ PHP infere automaticamente que é 'string' 
    // porque Blueprint::CREATED_AT é const string
    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    const DELETED_AT = Blueprint::DELETED_AT;
    
    // ❌ NÃO precisa fazer isso:
    // const string CREATED_AT = Blueprint::CREATED_AT;
}
```

**Por que funciona?**
- Quando você atribui `Blueprint::CREATED_AT` (que é `const string`), o PHP infere automaticamente o tipo
- Você **pode** declarar explicitamente se quiser: `const string CREATED_AT = Blueprint::CREATED_AT;`
- Mas **não é obrigatório** e adiciona redundância desnecessária

## 3. Traits com Métodos Auxiliares

### Trait `StoreTimestamps` (opcional):

```php
trait StoreTimestamps
{
    // Métodos auxiliares que retornam os nomes das colunas
    public function getCreatedAtColumn(): string
    {
        return static::CREATED_AT ?? Blueprint::CREATED_AT;
    }
}
```

### No Model:

```php
class PessoaModel extends Model
{
    use StoreTimestamps;  // Opcional - apenas se precisar dos métodos auxiliares
    
    // Ainda precisa declarar as constantes (Eloquent requer)
    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
}
```

**Nota:** O trait `StoreTimestamps` é opcional. A maioria dos modelos não precisa dele, apenas declara as constantes diretamente.

## 4. Trait `HasTimestampsCustomizados` (para casts)

### Trait:

```php
trait HasTimestampsCustomizados
{
    protected function getTimestampsCasts(): array
    {
        return [
            Blueprint::CREATED_AT => 'datetime',
            Blueprint::UPDATED_AT => 'datetime',
            Blueprint::DELETED_AT => 'datetime',
        ];
    }
}
```

### No Model:

```php
class Orgao extends Model
{
    use SoftDeletes, HasTimestampsCustomizados;
    
    // Constantes sem tipos explícitos
    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    const DELETED_AT = Blueprint::DELETED_AT;
    
    protected function casts(): array
    {
        return array_merge($this->getTimestampsCasts(), [
            'emails' => 'array',
            'telefones' => 'array',
        ]);
    }
}
```

## 5. Herança e Convenções do Laravel

### Classe Base Model:

O Laravel usa convenções e métodos mágicos, então muitas propriedades não precisam de tipos:

```php
class MeuModel extends Model
{
    // ✅ Não precisa: protected string $table
    protected $table = 'minha_tabela';
    
    // ✅ Não precisa: protected array $fillable
    protected $fillable = ['campo1', 'campo2'];
    
    // O Laravel infere através de convenções:
    // - Nome da tabela: plural do nome da classe
    // - Primary key: 'id' por padrão
    // - Timestamps: através de constantes CREATED_AT e UPDATED_AT
}
```

## 6. Quando Declarar Tipos Explicitamente

O sistema **só declara tipos explicitamente** quando:

### ✅ **DEVE declarar tipos quando:**
- Parâmetros de métodos públicos (type safety)
- Retornos de métodos públicos (type safety)
- Propriedades que precisam de type safety em interfaces/contratos
- Constantes na classe base (Blueprint) que são a fonte da verdade

### ❌ **NÃO precisa declarar tipos quando:**
- Atribuindo constantes tipadas de outras classes (PHP infere)
- Propriedades que seguem convenções do Laravel
- Constantes em modelos que apenas referenciam Blueprint
- Métodos onde o tipo pode ser inferido

## Exemplos Práticos

### ✅ Correto (sem tipos explícitos):

```php
class CustoIndireto extends Model
{
    use SoftDeletes, HasEmpresaScope;
    
    // PHP infere automaticamente que são strings
    const CREATED_AT = Blueprint::CREATED_AT;
    const UPDATED_AT = Blueprint::UPDATED_AT;
    const DELETED_AT = Blueprint::DELETED_AT;
    
    protected $table = 'custo_indiretos';
    protected $fillable = ['descricao', 'valor'];
}
```

### ✅ Também correto (com tipos explícitos, mas redundante):

```php
class CustoIndireto extends Model
{
    // Funciona, mas é redundante - PHP já infere
    const string CREATED_AT = Blueprint::CREATED_AT;
    const string UPDATED_AT = Blueprint::UPDATED_AT;
    const string DELETED_AT = Blueprint::DELETED_AT;
}
```

### ❌ Errado (sem usar Blueprint):

```php
class CustoIndireto extends Model
{
    // ❌ Não use strings literais - use Blueprint
    const CREATED_AT = 'criado_em';
    const UPDATED_AT = 'atualizado_em';
}
```

## Resumo

1. **Blueprint** declara tipos explicitamente (fonte da verdade)
2. **Modelos** não precisam declarar tipos nas constantes (PHP infere)
3. **Traits** fornecem métodos auxiliares quando necessário
4. **Laravel** usa convenções para muitas propriedades
5. **Type hints** apenas quando necessário para type safety

Este padrão mantém o código limpo, aproveita a inferência de tipos do PHP 8.1+, e garante consistência em todo o sistema.





