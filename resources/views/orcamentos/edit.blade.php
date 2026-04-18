@extends('layouts.app')

@section('title', 'Editar Orçamento - Item ' . $item->numero_item)

@section('content')
<div class="mb-8 flex items-center gap-4">
    <a href="{{ route('processos.show', $processo) }}" class="text-gray-600 hover:text-gray-900">
        <x-heroicon-o-arrow-left class="w-6 h-6" />
    </a>
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Editar Orçamento</h1>
        <p class="text-gray-600 mt-1">Item {{ $item->numero_item }} - {{ $processo->numero_modalidade }}</p>
    </div>
</div>

<div class="card p-8">
    <form method="POST" action="{{ route('orcamentos.update', [$processo, $item, $orcamento]) }}">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Fornecedor <span class="text-red-500">*</span>
                </label>
                <select name="fornecedor_id" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    <option value="">Selecione um fornecedor</option>
                    @foreach($fornecedores as $fornecedor)
                    <option value="{{ $fornecedor->id }}" {{ old('fornecedor_id', $orcamento->fornecedor_id) == $fornecedor->id ? 'selected' : '' }}>
                        {{ $fornecedor->razao_social }}
                    </option>
                    @endforeach
                </select>
                @error('fornecedor_id')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Custo do Produto <span class="text-red-500">*</span>
                </label>
                <input type="number" name="custo_produto" required step="0.01" min="0"
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
                    Marca/Modelo
                </label>
                <input type="text" name="marca_modelo" maxlength="255"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('marca_modelo', $orcamento->marca_modelo) }}" placeholder="Marca e modelo oferecido">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Frete
                </label>
                <input type="number" name="frete" step="0.01" min="0"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('frete', $orcamento->frete) }}" placeholder="0.00">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Transportadora
                </label>
                <select name="transportadora_id"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    <option value="">Nenhuma (frete do fornecedor)</option>
                    @foreach($transportadoras as $transportadora)
                    <option value="{{ $transportadora->id }}" {{ old('transportadora_id', $orcamento->transportadora_id) == $transportadora->id ? 'selected' : '' }}>
                        {{ $transportadora->razao_social }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="frete_incluido" value="1" 
                           {{ old('frete_incluido', $orcamento->frete_incluido) ? 'checked' : '' }}
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <span class="text-sm font-semibold text-gray-700">Frete incluído no custo do produto</span>
                </label>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Ajustes na Especificação
                </label>
                <textarea name="ajustes_especificacao" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                          placeholder="Descreva ajustes ou variações na especificação técnica...">{{ old('ajustes_especificacao', $orcamento->ajustes_especificacao) }}</textarea>
            </div>

            <div class="md:col-span-2">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="fornecedor_escolhido" value="1" 
                           {{ old('fornecedor_escolhido', $orcamento->fornecedor_escolhido) ? 'checked' : '' }}
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <span class="text-sm font-semibold text-gray-700">Marcar como fornecedor escolhido</span>
                </label>
                <p class="text-xs text-gray-500 mt-1">Ao marcar, os outros orçamentos deste item serão desmarcados</p>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Observações</label>
                <textarea name="observacoes" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                          placeholder="Observações adicionais...">{{ old('observacoes', $orcamento->observacoes) }}</textarea>
            </div>
        </div>

        <div class="mt-8 flex gap-4 pt-6 border-t border-gray-200">
            <button type="submit" class="btn-primary text-white px-8 py-3 rounded-lg font-semibold flex items-center gap-2 shadow-lg">
                <x-heroicon-o-check class="w-5 h-5" />
                Atualizar Orçamento
            </button>
            <a href="{{ route('processos.show', $processo) }}" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
