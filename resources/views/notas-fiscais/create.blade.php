@extends('layouts.app')

@section('title', 'Nova Nota Fiscal - ' . $processo->numero_modalidade)

@section('content')
<div class="mb-8 flex items-center gap-4">
    <a href="{{ route('processos.show', $processo) }}" class="text-gray-600 hover:text-gray-900">
        <x-heroicon-o-arrow-left class="w-6 h-6" />
    </a>
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Nova Nota Fiscal</h1>
        <p class="text-gray-600 mt-1">Processo: {{ $processo->numero_modalidade }}</p>
    </div>
</div>

<div class="card p-8">
    <form method="POST" action="{{ route('notas-fiscais.store', $processo) }}" enctype="multipart/form-data">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Tipo <span class="text-red-500">*</span>
                </label>
                <select name="tipo" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    <option value="entrada" {{ old('tipo') == 'entrada' ? 'selected' : '' }}>Entrada (Custo)</option>
                    <option value="saida" {{ old('tipo') == 'saida' ? 'selected' : '' }}>Saída (Faturamento)</option>
                </select>
                @error('tipo')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Número <span class="text-red-500">*</span>
                </label>
                <input type="text" name="numero" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('numero') }}" placeholder="Número da nota fiscal">
                @error('numero')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Série</label>
                <input type="text" name="serie" maxlength="10"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('serie') }}" placeholder="Série">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Data de Emissão <span class="text-red-500">*</span>
                </label>
                <input type="date" name="data_emissao" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('data_emissao') }}">
                @error('data_emissao')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Fornecedor
                </label>
                <select name="fornecedor_id"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    <option value="">Selecione um fornecedor</option>
                    @foreach($fornecedores as $fornecedor)
                    <option value="{{ $fornecedor->id }}" {{ old('fornecedor_id') == $fornecedor->id ? 'selected' : '' }}>
                        {{ $fornecedor->razao_social }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Valor <span class="text-red-500">*</span>
                </label>
                <input type="number" name="valor" required step="0.01" min="0"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('valor') }}" placeholder="0.00">
                @error('valor')
                    <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
                        <x-heroicon-s-exclamation-circle class="w-4 h-4" />
                        {{ $message }}
                    </p>
                @enderror
            </div>

            @if($empenhos->count() > 0)
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Empenho (opcional)
                </label>
                <select name="empenho_id"
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    <option value="">Nenhum</option>
                    @foreach($empenhos as $empenho)
                    <option value="{{ $empenho->id }}" {{ old('empenho_id') == $empenho->id ? 'selected' : '' }}>
                        {{ $empenho->numero }} - R$ {{ number_format($empenho->valor, 2, ',', '.') }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Situação <span class="text-red-500">*</span>
                </label>
                <select name="situacao" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    <option value="pendente" {{ old('situacao', 'pendente') == 'pendente' ? 'selected' : '' }}>Pendente</option>
                    <option value="paga" {{ old('situacao') == 'paga' ? 'selected' : '' }}>Paga</option>
                    <option value="cancelada" {{ old('situacao') == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Data de Pagamento
                </label>
                <input type="date" name="data_pagamento"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" 
                       value="{{ old('data_pagamento') }}">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Arquivo <span class="text-gray-500 text-xs">(PDF, JPG, PNG - Máx. 10MB)</span>
                </label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-blue-400 transition-colors">
                    <div class="space-y-1 text-center">
                        <x-heroicon-o-photo class="mx-auto h-10 w-10 text-gray-400" />
                        <div class="flex text-sm text-gray-600">
                            <label for="arquivo" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                <span>Selecione um arquivo</span>
                                <input id="arquivo" name="arquivo" type="file" accept=".pdf,.jpg,.jpeg,.png" class="sr-only">
                            </label>
                            <p class="pl-1">ou arraste e solte</p>
                        </div>
                        <p class="text-xs text-gray-500">PNG, JPG, PDF até 10MB</p>
                    </div>
                </div>
                @error('arquivo')
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
                Salvar Nota Fiscal
            </button>
            <a href="{{ route('processos.show', $processo) }}" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
