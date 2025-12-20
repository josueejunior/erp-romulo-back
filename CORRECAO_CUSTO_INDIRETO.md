# 🔧 Correção: Custo Indireto não aparecendo na listagem

## Problema Identificado

O método `store()` do `CustoIndiretoController` não estava adicionando `empresa_id` ao criar novos registros, fazendo com que:
1. Os registros fossem criados com `empresa_id = NULL`
2. A listagem filtra por `empresa_id`, então registros com `NULL` não aparecem

## Correção Aplicada

### 1. Método `store()` - Corrigido
```php
public function store(Request $request)
{
    $empresa = $this->getEmpresaAtivaOrFail(); // ✅ Adicionado
    
    // ... validação ...
    
    $data = $request->all();
    $data['empresa_id'] = $empresa->id; // ✅ Adicionado
    $custo = CustoIndireto::create($data);
}
```

### 2. Método `update()` - Corrigido
```php
public function update(Request $request, $id)
{
    $empresa = $this->getEmpresaAtivaOrFail(); // ✅ Adicionado
    $custo = CustoIndireto::where('id', $id)
        ->where('empresa_id', $empresa->id) // ✅ Validação adicionada
        ->firstOrFail();
}
```

## ⚠️ Dados Existentes

Se você criou custos indiretos antes desta correção, eles podem ter `empresa_id = NULL` e não aparecerão na listagem.

### Solução: Atualizar Dados Existentes

Execute este comando SQL no banco do tenant para atribuir `empresa_id` aos registros existentes:

```sql
-- Substitua EMPRESA_ID pelo ID da empresa ativa
UPDATE custo_indiretos 
SET empresa_id = EMPRESA_ID 
WHERE empresa_id IS NULL;
```

Ou crie um script de migração para fazer isso automaticamente.

## ✅ Teste

Agora, ao criar um novo custo indireto:
1. O `empresa_id` será automaticamente atribuído
2. O registro aparecerá na listagem filtrada por empresa
3. O registro só será visível para a empresa que o criou

