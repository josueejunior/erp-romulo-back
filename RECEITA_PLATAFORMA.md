# 📊 Receita da Plataforma - Explicação

## O Que É

O campo **"Receita Mensal"** (também chamado de "Receita da Plataforma") exibido na tela de **Gerenciar Assinaturas** (`AdminAssinaturas`) representa a **soma total dos valores pagos por todas as assinaturas ativas** no sistema.

## De Onde Vem

### Localização no Código

**Frontend**: `erp-romulo-front/src/pages/admin/AdminAssinaturas.jsx` (linhas 378-387)

```javascript
<Card padding="md">
  <div className="text-sm text-gray-600 mb-1">Receita Mensal</div>
  <div className="text-2xl font-bold text-blue-600">
    {formatarPreco(
      assinaturas
        .filter(a => a.status === 'ativa')
        .reduce((sum, a) => sum + (a.valor_pago || 0), 0)
    )}
  </div>
  <div className="text-xs text-gray-500 mt-1">
    Soma de todas as assinaturas ativas
  </div>
</Card>
```

### Como É Calculado

1. **Filtra assinaturas ativas**: `assinaturas.filter(a => a.status === 'ativa')`
2. **Soma os valores pagos**: `.reduce((sum, a) => sum + (a.valor_pago || 0), 0)`
3. **Formata como moeda**: `formatarPreco()` exibe em R$ (BRL)

### Fonte dos Dados

Os dados vêm do campo `valor_pago` da tabela `assinaturas`, que é preenchido quando:
- Uma assinatura é criada via pagamento (Mercado Pago)
- Uma assinatura é renovada
- Um admin atualiza manualmente o valor (via edição)

## Observações Importantes

⚠️ **Atenção**: Este valor representa a receita **total acumulada** de assinaturas ativas, não necessariamente a receita mensal recorrente (MRR).

### Para Calcular MRR Real

Se você quiser calcular a **Receita Mensal Recorrente (MRR)** real, seria necessário:
1. Considerar apenas o valor mensal do plano (não o valor pago total)
2. Multiplicar pelo número de assinaturas ativas
3. Considerar planos anuais (dividir por 12)

### Exemplo de Cálculo MRR

```javascript
// Receita Mensal Recorrente (MRR)
const mrr = assinaturas
  .filter(a => a.status === 'ativa')
  .reduce((sum, a) => {
    const plano = planos.find(p => p.id === a.plano_id);
    if (!plano) return sum;
    
    // Se tem preço mensal, usar ele
    if (plano.preco_mensal) {
      return sum + plano.preco_mensal;
    }
    
    // Se só tem preço anual, dividir por 12
    if (plano.preco_anual) {
      return sum + (plano.preco_anual / 12);
    }
    
    return sum;
  }, 0);
```

## Melhorias Futuras Sugeridas

1. **Adicionar cálculo de MRR** separado do valor total pago
2. **Gráfico de receita ao longo do tempo** (últimos 12 meses)
3. **Receita por plano** (quanto cada plano gera)
4. **Receita projetada** (baseada em assinaturas ativas)

