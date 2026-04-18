@extends('layouts.app')

@section('title', 'Empenho: ' . $empenho->numero)

@section('content')
<div class="mb-8 flex items-center gap-4">
    <a href="{{ route('processos.show', $processo) }}" class="text-gray-600 hover:text-gray-900">
        <x-heroicon-o-arrow-left class="w-6 h-6" />
    </a>
    <div>
        <h1 class="text-3xl font-bold text-gray-900">{{ $empenho->numero }}</h1>
        <p class="text-gray-600 mt-1">Processo: {{ $processo->numero_modalidade }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('empenhos.edit', [$processo, $empenho]) }}" 
           class="btn-primary text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2 shadow-lg">
            <x-heroicon-o-pencil class="w-5 h-5" />
            Editar
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="card p-6">
        <h2 class="text-xl font-bold mb-4">Informações do Empenho</h2>
        <div class="space-y-3">
            <div>
                <p class="text-sm text-gray-500">Número</p>
                <p class="font-semibold">{{ $empenho->numero }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Data</p>
                <p class="font-semibold">{{ $empenho->data->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Status</p>
                <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $empenho->concluido ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                    {{ $empenho->concluido ? 'Concluído' : 'Pendente' }}
                </span>
            </div>
            @if($empenho->data_entrega)
            <div>
                <p class="text-sm text-gray-500">Data de Entrega</p>
                <p class="font-semibold">{{ $empenho->data_entrega->format('d/m/Y') }}</p>
            </div>
            @endif
            @if($empenho->contrato)
            <div>
                <p class="text-sm text-gray-500">Contrato</p>
                <p class="font-semibold">{{ $empenho->contrato->numero }}</p>
            </div>
            @endif
            @if($empenho->autorizacaoFornecimento)
            <div>
                <p class="text-sm text-gray-500">Autorização de Fornecimento</p>
                <p class="font-semibold">{{ $empenho->autorizacaoFornecimento->numero }}</p>
            </div>
            @endif
        </div>
    </div>

    <div class="card p-6">
        <h2 class="text-xl font-bold mb-4">Valor</h2>
        <div class="space-y-3">
            <div>
                <p class="text-sm text-gray-500">Valor do Empenho</p>
                <p class="text-2xl font-bold text-gray-900">R$ {{ number_format($empenho->valor, 2, ',', '.') }}</p>
            </div>
        </div>
    </div>
</div>

@if($empenho->observacoes)
<div class="card p-6 mb-6">
    <h2 class="text-xl font-bold mb-4">Observações</h2>
    <p class="text-gray-700 whitespace-pre-line">{{ $empenho->observacoes }}</p>
</div>
@endif
@endsection
