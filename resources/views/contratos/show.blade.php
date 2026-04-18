@extends('layouts.app')

@section('title', 'Contrato: ' . $contrato->numero)

@section('content')
<div class="mb-8 flex items-center gap-4">
    <a href="{{ route('processos.show', $processo) }}" class="text-gray-600 hover:text-gray-900">
        <x-heroicon-o-arrow-left class="w-6 h-6" />
    </a>
    <div>
        <h1 class="text-3xl font-bold text-gray-900">{{ $contrato->numero }}</h1>
        <p class="text-gray-600 mt-1">Processo: {{ $processo->numero_modalidade }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('contratos.edit', [$processo, $contrato]) }}" 
           class="btn-primary text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2 shadow-lg">
            <x-heroicon-o-pencil class="w-5 h-5" />
            Editar
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="card p-6">
        <h2 class="text-xl font-bold mb-4">Informações do Contrato</h2>
        <div class="space-y-3">
            <div>
                <p class="text-sm text-gray-500">Número</p>
                <p class="font-semibold">{{ $contrato->numero }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Situação</p>
                <span class="px-3 py-1 text-sm font-semibold rounded-full 
                    {{ $contrato->situacao == 'vigente' ? 'bg-green-100 text-green-800' : 
                       ($contrato->situacao == 'encerrado' ? 'bg-gray-100 text-gray-800' : 'bg-red-100 text-red-800') }}">
                    {{ ucfirst($contrato->situacao) }}
                </span>
            </div>
            <div>
                <p class="text-sm text-gray-500">Data de Início</p>
                <p class="font-semibold">{{ $contrato->data_inicio->format('d/m/Y') }}</p>
            </div>
            @if($contrato->data_fim)
            <div>
                <p class="text-sm text-gray-500">Data de Fim</p>
                <p class="font-semibold">{{ $contrato->data_fim->format('d/m/Y') }}</p>
            </div>
            @endif
        </div>
    </div>

    <div class="card p-6">
        <h2 class="text-xl font-bold mb-4">Valores</h2>
        <div class="space-y-3">
            <div>
                <p class="text-sm text-gray-500">Valor Total</p>
                <p class="text-2xl font-bold text-gray-900">R$ {{ number_format($contrato->valor_total, 2, ',', '.') }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Saldo Disponível</p>
                <p class="text-2xl font-bold {{ $contrato->saldo >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    R$ {{ number_format($contrato->saldo, 2, ',', '.') }}
                </p>
            </div>
        </div>
    </div>
</div>

@if($contrato->empenhos->count() > 0)
<div class="card p-6 mb-6">
    <h2 class="text-xl font-bold mb-4">Empenhos Vinculados</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Número</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($contrato->empenhos as $empenho)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $empenho->numero }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $empenho->data->format('d/m/Y') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">R$ {{ number_format($empenho->valor, 2, ',', '.') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $empenho->concluido ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ $empenho->concluido ? 'Concluído' : 'Pendente' }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if($contrato->observacoes)
<div class="card p-6">
    <h2 class="text-xl font-bold mb-4">Observações</h2>
    <p class="text-gray-700 whitespace-pre-line">{{ $contrato->observacoes }}</p>
</div>
@endif
@endsection
