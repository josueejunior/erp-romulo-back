# 🔒 Sistema Robusto de Verificação de Dependências em Migrations

## Visão Geral

Este sistema previne erros de "relation does not exist" durante a execução de migrations, garantindo que todas as dependências sejam verificadas antes de criar foreign keys.

## Como Funciona

Todas as migrations que estendem `App\Database\Migrations\Migration` automaticamente têm acesso ao trait `HasDependencyChecks`, que fornece métodos para verificação segura de dependências.

## Métodos Disponíveis

### 1. `checkTablesExist($tables, $throwException = true)`

Verifica se uma ou mais tabelas existem antes de continuar.

```php
// Verificar uma tabela (lança exceção se não existir)
$this->checkTablesExist('users');

// Verificar múltiplas tabelas
$this->checkTablesExist(['users', 'empresas', 'processos']);

// Verificar sem lançar exceção (retorna array de tabelas faltantes)
$missing = $this->checkTablesExist(['users', 'empresas'], false);
if ($missing !== true) {
    // $missing contém array de tabelas que não existem
}
```

### 2. `addSafeForeignKeys($tableName, $foreignKeys)`

Adiciona foreign keys de forma segura após criar a tabela. Útil quando você precisa criar a tabela primeiro e adicionar foreign keys depois, quando as tabelas referenciadas já existirem.

```php
// Criar tabela primeiro
Schema::create('processo_item_vinculos', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('contrato_id')->nullable();
    $table->unsignedBigInteger('empenho_id')->nullable();
});

// Adicionar foreign keys depois (apenas se as tabelas existirem)
$this->addSafeForeignKeys('processo_item_vinculos', [
    ['column' => 'contrato_id', 'table' => 'contratos', 'nullable' => true],
    ['column' => 'empenho_id', 'table' => 'empenhos', 'nullable' => true],
]);
```

### 3. `safeForeign($table, $column, $referencedTable, ...)`

Cria uma foreign key de forma segura dentro de um `Schema::create()` ou `Schema::table()`.

```php
Schema::create('assinaturas', function (Blueprint $table) {
    $table->id();
    
    // Criar foreign key apenas se a tabela existir
    if ($this->safeForeign($table, 'user_id', 'users', 'id', 'cascade', false)) {
        // Foreign key criada com sucesso
    }
});
```

## Exemplos de Uso

### Exemplo 1: Migration com Dependências Obrigatórias

```php
public function up(): void
{
    // Verificar dependências obrigatórias
    $this->checkTablesExist('processo_itens');
    
    Schema::create('processo_item_vinculos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('processo_item_id')
            ->constrained('processo_itens')
            ->onDelete('cascade');
    });
}
```

### Exemplo 2: Migration com Dependências Opcionais

```php
public function up(): void
{
    // Criar tabela primeiro
    Schema::create('processo_item_vinculos', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('contrato_id')->nullable();
        $table->unsignedBigInteger('empenho_id')->nullable();
    });
    
    // Adicionar foreign keys depois (apenas se as tabelas existirem)
    $this->addSafeForeignKeys('processo_item_vinculos', [
        ['column' => 'contrato_id', 'table' => 'contratos'],
        ['column' => 'empenho_id', 'table' => 'empenhos'],
    ]);
}
```

### Exemplo 3: Verificação Condicional

```php
public function up(): void
{
    // Verificar sem lançar exceção
    $missing = $this->checkTablesExist(['users', 'empresas'], false);
    
    if ($missing === true) {
        // Todas as tabelas existem, criar foreign keys normalmente
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('empresa_id')->constrained('empresas');
        });
    } else {
        // Algumas tabelas não existem, criar apenas colunas
        Schema::table('assinaturas', function (Blueprint $table) {
            if (!Schema::hasColumn('assinaturas', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable();
            }
            if (!Schema::hasColumn('assinaturas', 'empresa_id')) {
                $table->unsignedBigInteger('empresa_id')->nullable();
            }
        });
        
        // Adicionar foreign keys depois
        $this->addSafeForeignKeys('assinaturas', [
            ['column' => 'user_id', 'table' => 'users'],
            ['column' => 'empresa_id', 'table' => 'empresas'],
        ]);
    }
}
```

## Ordem de Execução

As migrations são executadas na seguinte ordem (definida em `TenantDatabaseService::orderMigrationPaths`):

1. `permissions` - Tabelas de roles/permissions
2. `usuarios` - Tabela users
3. `empresas` - Tabela empresas
4. `fornecedores` - Tabelas de fornecedores
5. `orgaos` - Tabelas de órgãos
6. `documentos` - Tabelas de documentos
7. `processos` - Tabelas de processos
8. `contratos` - Tabelas de contratos
9. `autorizacoes_fornecimento` - Tabelas de autorizações
10. `empenhos` - Tabelas de empenhos
11. `orcamentos` - Tabelas de orçamentos
12. `notas_fiscais` - Tabelas de notas fiscais
13. `assinaturas` - Tabelas de assinaturas
14. Outras tabelas em ordem alfabética

## Boas Práticas

1. **Sempre verifique dependências obrigatórias**: Use `checkTablesExist()` para tabelas que são obrigatórias.

2. **Use `addSafeForeignKeys()` para dependências opcionais**: Quando uma foreign key é opcional (nullable), crie a coluna primeiro e adicione a foreign key depois.

3. **Logs automáticos**: O sistema registra automaticamente avisos quando tabelas não existem, facilitando o debug.

4. **Idempotência**: Os métodos verificam se foreign keys já existem antes de tentar criá-las, tornando as migrations idempotentes.

## Troubleshooting

Se você encontrar erros de "relation does not exist":

1. Verifique a ordem de execução das migrations
2. Use `checkTablesExist()` para verificar dependências obrigatórias
3. Use `addSafeForeignKeys()` para dependências opcionais
4. Verifique os logs para ver quais tabelas estão faltando

