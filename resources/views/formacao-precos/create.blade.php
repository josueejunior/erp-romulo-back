@extends('layouts.app')

@section('title', 'Formação de Preço - Item ' . $item->numero_item)

@section('content')
<div class="mb-8 flex items-center gap-4">
    <a href="{{ route('processos.show', $processo) }}" class="text-gray-600 hover:text-gray-900">
        <x-heroicon-o-arrow-left class="w-6 h-6" />
    </a>
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Formação de Preço</h1>
        <p class="text-gray-600 mt-1">Item {{ $item->numero_item }} - {{ $orcamento->fornecedor->razao_social }}</p>
    </div>
</div>

<div class="card p-8">
    <form method="POST" action="{{ route('formacao-precos.store', [$processo, $item, $orcamento]) }}" id="formFormacaoPreco">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Custo do Produto <span class="text-red-500">*</span>
                </label>
                <input type="number" name="custo_produto" id="custo_produto" required step="0.01" min="0"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('custo_produto', $orcamento->custo_produto) }}" placeholder="0.00">
                @error('custo_produto')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Frete <span class="text-red-500">*</span>
                </label>
                <input type="number" name="frete" id="frete" required step="0.01" min="0"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('frete', $orcamento->frete_incluido ? 0 : $orcamento->frete) }}" placeholder="0.00">
                @error('frete')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    % Impostos <span class="text-red-500">*</span>
                </label>
                <input type="number" name="percentual_impostos" id="percentual_impostos" required step="0.01" min="0" max="100"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('percentual_impostos', 0) }}" placeholder="0.00">
                @error('percentual_impostos')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    % Margem Desejada <span class="text-red-500">*</span>
                </label>
                <input type="number" name="percentual_margem" id="percentual_margem" required step="0.01" min="0" max="100"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('percentual_margem', 0) }}" placeholder="0.00">
                @error('percentual_margem')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="md:col-span-2 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Cálculo Automático</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Custo Total</p>
                        <p class="text-lg font-bold text-gray-900" id="custo_total">R$ 0,00</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Valor Impostos</p>
                        <p class="text-lg font-bold text-gray-900" id="valor_impostos">R$ 0,00</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Valor Margem</p>
                        <p class="text-lg font-bold text-gray-900" id="valor_margem">R$ 0,00</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Preço Final</p>
                        <p class="text-lg font-bold text-blue-600" id="preco_final">R$ 0,00</p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Preço Mínimo <span class="text-red-500">*</span>
                </label>
                <input type="number" name="preco_minimo" id="preco_minimo" required step="0.01" min="0"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('preco_minimo') }}" placeholder="0.00">
                <p class="text-xs text-gray-500 mt-1">Este valor será exibido no calendário de disputas</p>
                @error('preco_minimo')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Preço Recomendado
                </label>
                <input type="number" name="preco_recomendado" id="preco_recomendado" step="0.01" min="0"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('preco_recomendado') }}" placeholder="0.00">
                @error('preco_recomendado')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Observações</label>
                <textarea name="observacoes" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                          placeholder="Observações adicionais...">{{ old('observacoes') }}</textarea>
            </div>
        </div>

        <div class="mt-8 flex gap-4 pt-6 border-t border-gray-200">
            <button type="submit" class="btn-primary text-white px-8 py-3 rounded-lg font-semibold flex items-center gap-2 shadow-lg">
                <x-heroicon-o-check class="w-5 h-5" />
                Salvar Formação de Preço
            </button>
            <a href="{{ route('processos.show', $processo) }}" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                Cancelar
            </a>
        </div>
    </form>
</div>

<script>
function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor);
}

function calcularPreco() {
    const custoProduto = parseFloat(document.getElementById('custo_produto').value) || 0;
    const frete = parseFloat(document.getElementById('frete').value) || 0;
    const percentualImpostos = parseFloat(document.getElementById('percentual_impostos').value) || 0;
    const percentualMargem = parseFloat(document.getElementById('percentual_margem').value) || 0;

    const custoTotal = custoProduto + frete;
    const valorImpostos = (custoTotal * percentualImpostos) / 100;
    const custoComImpostos = custoTotal + valorImpostos;
    const valorMargem = (custoComImpostos * percentualMargem) / 100;
    const precoFinal = custoComImpostos + valorMargem;

    document.getElementById('custo_total').textContent = formatarMoeda(custoTotal);
    document.getElementById('valor_impostos').textContent = formatarMoeda(valorImpostos);
    document.getElementById('valor_margem').textContent = formatarMoeda(valorMargem);
    document.getElementById('preco_final').textContent = formatarMoeda(precoFinal);

    // Atualizar preço mínimo se estiver vazio
    const precoMinimo = document.getElementById('preco_minimo');
    if (!precoMinimo.value || precoMinimo.value == 0) {
        precoMinimo.value = precoFinal.toFixed(2);
    }
}

// Adicionar event listeners
['custo_produto', 'frete', 'percentual_impostos', 'percentual_margem'].forEach(id => {
    document.getElementById(id).addEventListener('input', calcularPreco);
});

// Calcular ao carregar
calcularPreco();
</script>
@endsection
