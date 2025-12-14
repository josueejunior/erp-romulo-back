@extends('layouts.app')

@section('title', 'Julgamento e Habilitação - ' . $processo->numero_modalidade)

@section('content')
<div class="mb-8 flex items-center gap-4">
    <a href="{{ route('processos.show', $processo) }}" class="text-gray-600 hover:text-gray-900">
        <x-heroicon-o-arrow-left class="w-6 h-6" />
    </a>
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Julgamento e Habilitação</h1>
        <p class="text-gray-600 mt-1">{{ $processo->numero_modalidade }}</p>
    </div>
</div>

<div class="card p-8">
    <form method="POST" action="{{ route('julgamentos.update', $processo) }}">
        @csrf
        @method('PUT')

        <div class="space-y-6">
            @foreach($processo->itens as $index => $item)
            <div class="border border-gray-200 rounded-lg p-6 bg-gray-50">
                <div class="mb-4">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Item {{ $item->numero_item }}</h3>
                    <p class="text-sm text-gray-700 mb-3">{{ Str::limit($item->especificacao_tecnica, 150) }}</p>
                    <div class="flex gap-4 text-xs text-gray-600 mb-3">
                        <span><strong>Quantidade:</strong> {{ $item->quantidade }} {{ $item->unidade }}</span>
                        @if($item->valor_final_sessao)
                        <span><strong>Valor Final Sessão:</strong> R$ {{ number_format($item->valor_final_sessao, 2, ',', '.') }}</span>
                        @endif
                        @if($item->classificacao)
                        <span><strong>Classificação:</strong> {{ $item->classificacao }}º lugar</span>
                        @endif
                    </div>
                </div>

                <input type="hidden" name="itens[{{ $index }}][id]" value="{{ $item->id }}">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Status do Item <span class="text-red-500">*</span>
                        </label>
                        <select name="itens[{{ $index }}][status_item]" required
                                class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <option value="pendente" {{ old("itens.{$index}.status_item", $item->status_item) == 'pendente' ? 'selected' : '' }}>Pendente</option>
                            <option value="aceito" {{ old("itens.{$index}.status_item", $item->status_item) == 'aceito' ? 'selected' : '' }}>Aceito</option>
                            <option value="aceito_habilitado" {{ old("itens.{$index}.status_item", $item->status_item) == 'aceito_habilitado' ? 'selected' : '' }}>Aceito e Habilitado</option>
                            <option value="desclassificado" {{ old("itens.{$index}.status_item", $item->status_item) == 'desclassificado' ? 'selected' : '' }}>Desclassificado</option>
                            <option value="inabilitado" {{ old("itens.{$index}.status_item", $item->status_item) == 'inabilitado' ? 'selected' : '' }}>Inabilitado</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Valor Negociado (pós-disputa)
                        </label>
                        <input type="number" name="itens[{{ $index }}][valor_negociado]" step="0.01" min="0"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                               value="{{ old("itens.{$index}.valor_negociado", $item->valor_negociado) }}" placeholder="0.00">
                        <p class="text-xs text-gray-500 mt-1">Valor negociado após a disputa (não apaga o valor anterior)</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Chance de Arremate
                        </label>
                        <select name="itens[{{ $index }}][chance_arremate]"
                                class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                            <option value="">Selecione</option>
                            <option value="baixa" {{ old("itens.{$index}.chance_arremate", $item->chance_arremate) == 'baixa' ? 'selected' : '' }}>Baixa</option>
                            <option value="media" {{ old("itens.{$index}.chance_arremate", $item->chance_arremate) == 'media' ? 'selected' : '' }}>Média</option>
                            <option value="alta" {{ old("itens.{$index}.chance_arremate", $item->chance_arremate) == 'alta' ? 'selected' : '' }}>Alta</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Chance Percentual (%)
                        </label>
                        <input type="number" name="itens[{{ $index }}][chance_percentual]" min="0" max="100"
                               class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                               value="{{ old("itens.{$index}.chance_percentual", $item->chance_percentual) }}" placeholder="0">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Lembretes</label>
                        <textarea name="itens[{{ $index }}][lembretes]" rows="2"
                                  class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                                  placeholder="Lembretes e observações sobre este item...">{{ old("itens.{$index}.lembretes", $item->lembretes) }}</textarea>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-8 flex gap-4 pt-6 border-t border-gray-200">
            <button type="submit" class="btn-primary text-white px-8 py-3 rounded-lg font-semibold flex items-center gap-2 shadow-lg">
                <x-heroicon-o-check class="w-5 h-5" />
                Salvar Julgamento
            </button>
            <a href="{{ route('processos.show', $processo) }}" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
