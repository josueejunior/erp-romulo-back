@extends('layouts.app')

@section('title', 'Nota Fiscal: ' . $notaFiscal->numero)

@section('content')
<div class="mb-8 flex items-center gap-4">
    <a href="{{ route('processos.show', $processo) }}" class="text-gray-600 hover:text-gray-900">
        <x-heroicon-o-arrow-left class="w-6 h-6" />
    </a>
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Nota Fiscal {{ $notaFiscal->numero }}</h1>
        <p class="text-gray-600 mt-1">Processo: {{ $processo->numero_modalidade }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('notas-fiscais.edit', [$processo, $notaFiscal]) }}" 
           class="btn-primary text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2 shadow-lg">
            <x-heroicon-o-pencil class="w-5 h-5" />
            Editar
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="card p-6">
        <h2 class="text-xl font-bold mb-4">Informações da Nota Fiscal</h2>
        <div class="space-y-3">
            <div>
                <p class="text-sm text-gray-500">Tipo</p>
                <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $notaFiscal->tipo == 'entrada' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                    {{ $notaFiscal->tipo == 'entrada' ? 'Entrada (Custo)' : 'Saída (Faturamento)' }}
                </span>
            </div>
            <div>
                <p class="text-sm text-gray-500">Número</p>
                <p class="font-semibold">{{ $notaFiscal->numero }}</p>
            </div>
            @if($notaFiscal->serie)
            <div>
                <p class="text-sm text-gray-500">Série</p>
                <p class="font-semibold">{{ $notaFiscal->serie }}</p>
            </div>
            @endif
            <div>
                <p class="text-sm text-gray-500">Data de Emissão</p>
                <p class="font-semibold">{{ $notaFiscal->data_emissao->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Situação</p>
                <span class="px-3 py-1 text-sm font-semibold rounded-full 
                    {{ $notaFiscal->situacao == 'paga' ? 'bg-green-100 text-green-800' : 
                       ($notaFiscal->situacao == 'cancelada' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                    {{ ucfirst($notaFiscal->situacao) }}
                </span>
            </div>
            @if($notaFiscal->data_pagamento)
            <div>
                <p class="text-sm text-gray-500">Data de Pagamento</p>
                <p class="font-semibold">{{ $notaFiscal->data_pagamento->format('d/m/Y') }}</p>
            </div>
            @endif
            @if($notaFiscal->fornecedor)
            <div>
                <p class="text-sm text-gray-500">Fornecedor</p>
                <p class="font-semibold">{{ $notaFiscal->fornecedor->razao_social }}</p>
            </div>
            @endif
            @if($notaFiscal->empenho)
            <div>
                <p class="text-sm text-gray-500">Empenho</p>
                <p class="font-semibold">{{ $notaFiscal->empenho->numero }}</p>
            </div>
            @endif
        </div>
    </div>

    <div class="card p-6">
        <h2 class="text-xl font-bold mb-4">Valor</h2>
        <div class="space-y-3">
            <div>
                <p class="text-sm text-gray-500">Valor da Nota Fiscal</p>
                <p class="text-2xl font-bold {{ $notaFiscal->tipo == 'entrada' ? 'text-red-600' : 'text-green-600' }}">
                    R$ {{ number_format($notaFiscal->valor, 2, ',', '.') }}
                </p>
            </div>
            @if($notaFiscal->arquivo)
            <div class="mt-4">
                <a href="{{ asset('storage/notas-fiscais/' . $notaFiscal->arquivo) }}" target="_blank" 
                   class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <x-heroicon-o-arrow-down-tray class="w-5 h-5" />
                    Baixar Arquivo
                </a>
            </div>
            @endif
        </div>
    </div>
</div>

@if($notaFiscal->observacoes)
<div class="card p-6">
    <h2 class="text-xl font-bold mb-4">Observações</h2>
    <p class="text-gray-700 whitespace-pre-line">{{ $notaFiscal->observacoes }}</p>
</div>
@endif
@endsection
