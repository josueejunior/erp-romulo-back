# 🔧 Melhorias Práticas - Implementações Sugeridas

## 🎯 Pontos Críticos para "Amarrar Melhor"

### 1. 🔒 **Transações de Banco de Dados**

#### Problema Atual
Operações que envolvem múltiplas tabelas não usam transações, podendo causar inconsistências.

#### Onde Implementar

**1.1 Criar Processo com Itens**
```php
// app/Http/Controllers/Api/ProcessoController.php - store()
DB::transaction(function () use ($validated, $request) {
    $processo = Processo::create($validated);
    
    // Salvar documentos de habilitação
    if ($request->has('documentos_habilitacao')) {
        $documentos = $request->input('documentos_habilitacao', []);
        foreach ($documentos as $docId) {
            $processo->documentos()->create([
                'documento_habilitacao_id' => $docId
            ]);
        }
    }
    
    return $processo;
});
```

**1.2 Criar Nota Fiscal com Validação de Vínculo**
```php
// app/Http/Controllers/Api/NotaFiscalController.php - store()
DB::transaction(function () use ($processo, $validated, $request) {
    // Validar que pelo menos um vínculo existe
    $temVinculo = $validated['empenho_id'] 
        || $validated['contrato_id'] 
        || $validated['autorizacao_fornecimento_id'];
    
    if (!$temVinculo) {
        throw new \Exception('Nota fiscal deve estar vinculada a um Empenho, Contrato ou AF.');
    }
    
    // Validar vínculos pertencem ao processo
    if ($validated['contrato_id']) {
        $contrato = Contrato::find($validated['contrato_id']);
        if (!$contrato || $contrato->processo_id !== $processo->id) {
            throw new \Exception('Contrato inválido.');
        }
    }
    
    // Criar nota fiscal
    $notaFiscal = NotaFiscal::create($validated);
    
    // Atualizar saldo do documento vinculado
    if ($validated['contrato_id']) {
        $contrato->atualizarSaldo();
    }
    
    return $notaFiscal;
});
```

**1.3 Criar Orçamento com Itens**
```php
// app/Http/Controllers/Api/OrcamentoController.php - store()
DB::transaction(function () use ($processo, $validated, $request) {
    $orcamento = Orcamento::create($validated);
    
    // Vincular itens do processo
    if ($request->has('itens_selecionados')) {
        $itensIds = $request->input('itens_selecionados', []);
        foreach ($itensIds as $itemId) {
            $item = ProcessoItem::where('processo_id', $processo->id)
                ->findOrFail($itemId);
            
            OrcamentoItem::create([
                'orcamento_id' => $orcamento->id,
                'processo_item_id' => $itemId,
                'custo_produto' => $item->valor_estimado ?? 0,
                // Outros campos...
            ]);
        }
    }
    
    return $orcamento;
});
```

---

### 2. ✅ **Validações Mais Robustas**

#### 2.1 Validação Customizada para Vínculos
```php
// app/Rules/ValidarVinculoProcesso.php (NOVO)
class ValidarVinculoProcesso implements Rule
{
    protected $processoId;
    protected $tipo;
    
    public function __construct($processoId, $tipo)
    {
        $this->processoId = $processoId;
        $this->tipo = $tipo; // 'contrato', 'empenho', 'af'
    }
    
    public function passes($attribute, $value)
    {
        if (!$value) return true; // Opcional
        
        switch ($this->tipo) {
            case 'contrato':
                $doc = Contrato::find($value);
                break;
            case 'empenho':
                $doc = Empenho::find($value);
                break;
            case 'af':
                $doc = AutorizacaoFornecimento::find($value);
                break;
            default:
                return false;
        }
        
        return $doc && $doc->processo_id === $this->processoId;
    }
    
    public function message()
    {
        return "O {$this->tipo} selecionado não pertence a este processo.";
    }
}

// Uso no controller:
$validated = $request->validate([
    'contrato_id' => [
        'nullable',
        new ValidarVinculoProcesso($processo->id, 'contrato')
    ],
    'autorizacao_fornecimento_id' => [
        'nullable',
        new ValidarVinculoProcesso($processo->id, 'af')
    ],
]);
```

#### 2.2 Validação de Valores Financeiros
```php
// app/Rules/ValidarValorTotal.php (NOVO)
class ValidarValorTotal implements Rule
{
    protected $custoProduto;
    protected $custoFrete;
    
    public function __construct($custoProduto, $custoFrete)
    {
        $this->custoProduto = $custoProduto;
        $this->custoFrete = $custoFrete;
    }
    
    public function passes($attribute, $value)
    {
        $totalEsperado = ($this->custoProduto ?? 0) + ($this->custoFrete ?? 0);
        return abs($value - $totalEsperado) < 0.01; // Tolerância para arredondamento
    }
    
    public function message()
    {
        return 'O custo total deve ser igual à soma de custo_produto + custo_frete.';
    }
}
```

#### 2.3 Validação de Status e Fase
```php
// app/Rules/ValidarFaseProcesso.php (NOVO)
class ValidarFaseProcesso implements Rule
{
    protected $processo;
    protected $fasesPermitidas;
    
    public function __construct(Processo $processo, array $fasesPermitidas)
    {
        $this->processo = $processo;
        $this->fasesPermitidas = $fasesPermitidas;
    }
    
    public function passes($attribute, $value)
    {
        return in_array($this->processo->status, $this->fasesPermitidas);
    }
    
    public function message()
    {
        return "Esta ação só é permitida nas fases: " . implode(', ', $this->fasesPermitidas);
    }
}
```

---

### 3. 🔄 **Observers para Atualização Automática**

#### 3.1 Observer para Contrato
```php
// app/Observers/ContratoObserver.php (NOVO)
class ContratoObserver
{
    public function created(Contrato $contrato)
    {
        $contrato->atualizarSaldo();
    }
    
    public function updated(Contrato $contrato)
    {
        $contrato->atualizarSaldo();
    }
}

// Registrar em AppServiceProvider:
use App\Models\Contrato;
use App\Observers\ContratoObserver;

public function boot()
{
    Contrato::observe(ContratoObserver::class);
}
```

#### 3.2 Observer para Nota Fiscal
```php
// app/Observers/NotaFiscalObserver.php (NOVO)
class NotaFiscalObserver
{
    public function created(NotaFiscal $notaFiscal)
    {
        $this->atualizarDocumentoVinculado($notaFiscal);
    }
    
    public function updated(NotaFiscal $notaFiscal)
    {
        $this->atualizarDocumentoVinculado($notaFiscal);
    }
    
    protected function atualizarDocumentoVinculado(NotaFiscal $notaFiscal)
    {
        if ($notaFiscal->contrato_id) {
            $notaFiscal->contrato->atualizarSaldo();
        }
        
        if ($notaFiscal->autorizacao_fornecimento_id) {
            $notaFiscal->autorizacaoFornecimento->atualizarSaldo();
        }
        
        if ($notaFiscal->empenho_id) {
            $notaFiscal->empenho->atualizarSaldo();
        }
    }
}
```

#### 3.3 Observer para Empenho
```php
// app/Observers/EmpenhoObserver.php (NOVO)
class EmpenhoObserver
{
    public function created(Empenho $empenho)
    {
        $this->atualizarContratoOuAF($empenho);
    }
    
    public function updated(Empenho $empenho)
    {
        $this->atualizarContratoOuAF($empenho);
    }
    
    protected function atualizarContratoOuAF(Empenho $empenho)
    {
        if ($empenho->contrato_id) {
            $empenho->contrato->atualizarSaldo();
        }
        
        if ($empenho->autorizacao_fornecimento_id) {
            $empenho->autorizacaoFornecimento->atualizarSaldo();
        }
    }
}
```

---

### 4. 📊 **Cálculos Automáticos com Accessors**

#### 4.1 Accessor para Valor Total do Item
```php
// app/Models/ProcessoItem.php
public function getValorEstimadoTotalAttribute(): float
{
    $quantidade = $this->quantidade ?? 0;
    $valorUnitario = $this->valor_estimado ?? 0;
    return round($quantidade * $valorUnitario, 2);
}

// Sempre recalcular ao salvar
protected static function booted()
{
    static::saving(function ($item) {
        if ($item->isDirty(['quantidade', 'valor_estimado'])) {
            $item->valor_estimado_total = $item->valor_estimado_total;
        }
    });
}
```

#### 4.2 Accessor para Custo Total da Nota Fiscal
```php
// app/Models/NotaFiscal.php
public function getCustoTotalAttribute(): float
{
    $produto = $this->custo_produto ?? 0;
    $frete = $this->custo_frete ?? 0;
    return round($produto + $frete, 2);
}

// Validar ao salvar
protected static function booted()
{
    static::saving(function ($nota) {
        if ($nota->custo_total !== ($nota->custo_produto + $nota->custo_frete)) {
            $nota->custo_total = $nota->custo_produto + $nota->custo_frete;
        }
    });
}
```

---

### 5. 🎨 **Melhorias de UX no Frontend**

#### 5.1 Validação em Tempo Real
```jsx
// Exemplo: ProcessoForm.jsx
const [errors, setErrors] = useState({});

const validateField = (field, value) => {
  const newErrors = { ...errors };
  
  switch (field) {
    case 'numero_modalidade':
      if (!value || value.trim() === '') {
        newErrors.numero_modalidade = 'Número da modalidade é obrigatório';
      } else {
        delete newErrors.numero_modalidade;
      }
      break;
    case 'data_hora_sessao_publica':
      if (!value) {
        newErrors.data_hora_sessao_publica = 'Data e hora são obrigatórias';
      } else if (new Date(value) < new Date()) {
        newErrors.data_hora_sessao_publica = 'Data não pode ser no passado';
      } else {
        delete newErrors.data_hora_sessao_publica;
      }
      break;
  }
  
  setErrors(newErrors);
};

// No input:
<input
  value={formData.numero_modalidade}
  onChange={(e) => {
    setFormData({ ...formData, numero_modalidade: e.target.value });
    validateField('numero_modalidade', e.target.value);
  }}
  className={`w-full border rounded-lg px-4 py-2 ${
    errors.numero_modalidade ? 'border-red-500' : 'border-gray-300'
  }`}
/>
{errors.numero_modalidade && (
  <p className="text-red-500 text-sm mt-1">{errors.numero_modalidade}</p>
)}
```

#### 5.2 Feedback Visual de Status
```jsx
// Componente: StatusBadge.jsx
const StatusBadge = ({ status }) => {
  const statusConfig = {
    participacao: { color: 'blue', icon: CalendarIcon, label: 'Participação' },
    julgamento_habilitacao: { color: 'yellow', icon: ClockIcon, label: 'Julgamento' },
    execucao: { color: 'green', icon: CheckCircleIcon, label: 'Execução' },
    pagamento: { color: 'purple', icon: CurrencyDollarIcon, label: 'Pagamento' },
    encerramento: { color: 'gray', icon: ArchiveIcon, label: 'Encerrado' },
  };
  
  const config = statusConfig[status] || { color: 'gray', label: status };
  
  return (
    <span className={`inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-${config.color}-100 text-${config.color}-800`}>
      <config.icon className="w-3 h-3" />
      {config.label}
    </span>
  );
};
```

---

### 6. 🔐 **Validação de Permissões Mais Rigorosa**

#### 6.1 Policy para Processo
```php
// app/Policies/ProcessoPolicy.php (NOVO)
class ProcessoPolicy
{
    public function update(User $user, Processo $processo)
    {
        // Verificar permissão específica
        if (!PermissionHelper::canManageProcess()) {
            return false;
        }
        
        // Verificar se processo pode ser editado
        if ($processo->isEmExecucao() && !request()->has('data_recebimento_pagamento')) {
            return false;
        }
        
        return true;
    }
    
    public function delete(User $user, Processo $processo)
    {
        // Só pode deletar se não estiver em execução
        return !$processo->isEmExecucao() && PermissionHelper::canManageProcess();
    }
    
    public function changeStatus(User $user, Processo $processo)
    {
        return PermissionHelper::canMarkProcessStatus();
    }
}
```

---

### 7. 📝 **Validação de Regras de Negócio**

#### 7.1 Validar Orçamento Escolhido
```php
// app/Http/Controllers/Api/OrcamentoController.php
public function marcarComoEscolhido(Request $request, Processo $processo, Orcamento $orcamento)
{
    // Validar que orçamento pertence ao processo
    if ($orcamento->processo_id !== $processo->id) {
        return response()->json(['message' => 'Orçamento inválido.'], 400);
    }
    
    // Validar que processo está em participação
    if ($processo->status !== 'participacao') {
        return response()->json([
            'message' => 'Orçamentos só podem ser escolhidos na fase de participação.'
        ], 403);
    }
    
    DB::transaction(function () use ($orcamento, $processo) {
        // Desmarcar outros orçamentos do mesmo item
        $itemIds = $orcamento->itens->pluck('processo_item_id');
        
        Orcamento::where('processo_id', $processo->id)
            ->where('id', '!=', $orcamento->id)
            ->whereHas('itens', function ($q) use ($itemIds) {
                $q->whereIn('processo_item_id', $itemIds);
            })
            ->update(['fornecedor_escolhido' => false]);
        
        // Marcar este como escolhido
        $orcamento->update(['fornecedor_escolhido' => true]);
    });
    
    return response()->json(['message' => 'Orçamento marcado como escolhido.']);
}
```

#### 7.2 Validar Transição de Status
```php
// app/Http/Controllers/Api/ProcessoController.php - update()
public function update(Request $request, Processo $processo)
{
    // Se está mudando status, validar transição
    if ($request->has('status') && $request->status !== $processo->status) {
        $validacao = $this->statusService->podeAlterarStatus($processo, $request->status);
        if (!$validacao['pode']) {
            return response()->json([
                'message' => $validacao['motivo']
            ], 400);
        }
    }
    
    // Resto da lógica...
}
```

---

### 8. 🧮 **Cálculos Financeiros Precisos**

#### 8.1 Service para Cálculos
```php
// app/Services/CalculoFinanceiroService.php (NOVO)
class CalculoFinanceiroService
{
    public function calcularMargemBruta(float $receita, float $custosDiretos): float
    {
        if ($receita == 0) return 0;
        return round((($receita - $custosDiretos) / $receita) * 100, 2);
    }
    
    public function calcularMargemLiquida(float $receita, float $custosDiretos, float $custosIndiretos): float
    {
        if ($receita == 0) return 0;
        return round((($receita - $custosDiretos - $custosIndiretos) / $receita) * 100, 2);
    }
    
    public function validarSomaValores(array $valores, float $totalEsperado, float $tolerancia = 0.01): bool
    {
        $soma = array_sum($valores);
        return abs($soma - $totalEsperado) < $tolerancia;
    }
}
```

---

## 🎯 RESUMO DAS MELHORIAS

### 🔴 **CRÍTICO** (Implementar Primeiro)

1. ✅ **Transações de Banco de Dados**
   - Criar/Atualizar Processo com itens
   - Criar/Atualizar Nota Fiscal
   - Criar/Atualizar Orçamento com itens

2. ✅ **Validação de Vínculos Hierárquicos**
   - Validar que documentos pertencem ao processo
   - Validar que notas fiscais estão vinculadas corretamente

3. ✅ **Observers para Atualização Automática**
   - Atualizar saldos automaticamente
   - Manter consistência de dados

### 🟡 **IMPORTANTE** (Implementar Depois)

4. ✅ **Validações Customizadas**
   - Regras de validação reutilizáveis
   - Validações financeiras

5. ✅ **Cálculos Automáticos**
   - Accessors para valores calculados
   - Recalcular automaticamente

6. ✅ **Melhorias de UX**
   - Validação em tempo real
   - Feedback visual melhor

---

## 📝 PRÓXIMOS PASSOS

1. **Criar Rules customizadas** para validações complexas
2. **Implementar Observers** para atualização automática
3. **Adicionar transações** nas operações críticas
4. **Melhorar validações** no frontend
5. **Criar Policies** para controle de acesso

---

## ✨ RESULTADO ESPERADO

Com essas melhorias, o sistema terá:
- ✅ **100% de integridade de dados** (transações)
- ✅ **Validações robustas** (regras customizadas)
- ✅ **Atualizações automáticas** (observers)
- ✅ **Melhor UX** (validação em tempo real)
- ✅ **Código mais limpo** (separação de responsabilidades)

**Status**: Sistema funcional → Sistema robusto e profissional

