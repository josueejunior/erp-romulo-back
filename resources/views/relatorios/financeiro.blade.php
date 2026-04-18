@extends('layouts.app')

@section('title', 'Relatórios Financeiros')

@section('content')
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Relatórios Financeiros</h1>
    <p class="text-gray-600">Análise financeira dos processos em execução</p>
</div>

<div class="card p-6 mb-6">
    <form method="GET" class="flex gap-4 flex-wrap">
        <input type="date" name="data_inicio" value="{{ request('data_inicio') }}"
               class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
               placeholder="Data Início">
        <input type="date" name="data_fim" value="{{ request('data_fim') }}"
               class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
               placeholder="Data Fim">
        <button type="submit" class="bg-gray-700 text-white px-6 py-2 rounded-lg hover:bg-gray-800 transition-colors font-medium">
            Filtrar
        </button>
        @if(request('data_inicio') || request('data_fim'))
        <a href="{{ route('relatorios.financeiro') }}" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition-colors font-medium">
            Limpar
        </a>
        @endif
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="card p-6 border-l-4 border-green-500">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-gray-600">Total a Receber</p>
            <x-heroicon-o-currency-dollar class="w-6 h-6 text-green-500" />
        </div>
        <p class="text-3xl font-bold text-green-600">R$ {{ number_format($totalReceber, 2, ',', '.') }}</p>
        @if(isset($totalSaldoReceber) && $totalSaldoReceber > 0)
        <p class="text-xs text-gray-500 mt-1">Saldo pendente: R$ {{ number_format($totalSaldoReceber, 2, ',', '.') }}</p>
        @endif
    </div>
    <div class="card p-6 border-l-4 border-red-500">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-gray-600">Custos Diretos</p>
            <x-heroicon-o-wallet class="w-6 h-6 text-red-500" />
        </div>
        <p class="text-3xl font-bold text-red-600">R$ {{ number_format($totalCustosDiretos, 2, ',', '.') }}</p>
    </div>
    <div class="card p-6 border-l-4 border-orange-500">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-gray-600">Custos Indiretos</p>
            <x-heroicon-o-building-office class="w-6 h-6 text-orange-500" />
        </div>
        <p class="text-3xl font-bold text-orange-600">R$ {{ number_format($totalCustosIndiretos, 2, ',', '.') }}</p>
    </div>
    <div class="card p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-medium text-gray-600">Lucro Líquido</p>
            <x-heroicon-o-arrow-trending-up class="w-6 h-6 {{ $lucroLiquido >= 0 ? 'text-blue-500' : 'text-red-500' }}" />
        </div>
        <p class="text-3xl font-bold {{ $lucroLiquido >= 0 ? 'text-blue-600' : 'text-red-600' }}">
            R$ {{ number_format($lucroLiquido, 2, ',', '.') }}
        </p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="card p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Margem Bruta</h3>
        <div class="flex items-end gap-4">
            <p class="text-4xl font-bold text-blue-600">{{ number_format($margemBruta, 2, ',', '.') }}%</p>
            <div class="flex-1">
                <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
                    <div class="bg-blue-600 h-3 rounded-full" style="width: {{ min($margemBruta, 100) }}%"></div>
                </div>
            </div>
        </div>
        <p class="text-sm text-gray-600 mt-4">
            Lucro Bruto: <span class="font-semibold text-gray-900">R$ {{ number_format($lucroBruto, 2, ',', '.') }}</span>
        </p>
    </div>
    <div class="card p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-4">Margem Líquida</h3>
        <div class="flex items-end gap-4">
            <p class="text-4xl font-bold {{ $margemLiquida >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ number_format($margemLiquida, 2, ',', '.') }}%
            </p>
            <div class="flex-1">
                <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
                    <div class="h-3 rounded-full {{ $margemLiquida >= 0 ? 'bg-green-600' : 'bg-red-600' }}" 
                         style="width: {{ min(abs($margemLiquida), 100) }}%"></div>
                </div>
            </div>
        </div>
        <p class="text-sm text-gray-600 mt-4">
            Lucro Líquido: <span class="font-semibold text-gray-900">R$ {{ number_format($lucroLiquido, 2, ',', '.') }}</span>
        </p>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="p-6 bg-gradient-to-r from-gray-50 to-gray-100 border-b">
        <h2 class="text-xl font-bold text-gray-900">Processos em Execução</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Processo</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Receita</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Custos Diretos</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Lucro</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Margem</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($processos as $processo)
                @php
                    // Receita: valor dos contratos ou valores negociados dos itens
                    if ($processo->contratos->count() > 0) {
                        $receita = $processo->contratos->sum('valor_total');
                        $saldoReceber = $processo->contratos->sum('saldo');
                    } else {
                        $receita = $processo->itens->sum(function($item) {
                            return $item->valor_negociado ?? $item->valor_final_sessao ?? 0;
                        });
                        $saldoReceber = $receita; // Se não tem contrato, considera tudo como pendente
                    }
                    $custosDiretos = $processo->notasFiscais->where('tipo', 'entrada')->sum('valor');
                    $lucro = $receita - $custosDiretos;
                    $margem = $receita > 0 ? ($lucro / $receita) * 100 : 0;
                @endphp
                <tr class="table-row transition-colors">
                    <td class="px-6 py-4">
                        <div class="text-sm font-semibold text-gray-900">{{ $processo->numero_modalidade }}</div>
                        <div class="text-xs text-gray-500 max-w-md">{{ Str::limit($processo->objeto_resumido, 40) }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">R$ {{ number_format($receita, 2, ',', '.') }}</div>
                        @if(isset($saldoReceber) && $saldoReceber < $receita)
                        <div class="text-xs text-gray-500">Saldo: R$ {{ number_format($saldoReceber, 2, ',', '.') }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">R$ {{ number_format($custosDiretos, 2, ',', '.') }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-bold {{ $lucro >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            R$ {{ number_format($lucro, 2, ',', '.') }}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-gray-200 rounded-full h-2 max-w-[100px]">
                                <div class="h-2 rounded-full {{ $margem >= 0 ? 'bg-green-600' : 'bg-red-600' }}" 
                                     style="width: {{ min(abs($margem), 100) }}%"></div>
                            </div>
                            <span class="text-sm font-semibold {{ $margem >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($margem, 1) }}%
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('processos.show', $processo) }}" class="text-blue-600 hover:text-blue-900 font-medium">
                            Ver
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center">
                        <x-heroicon-o-chart-bar class="w-8 h-8 text-gray-400 mx-auto mb-4" />
                        <p class="text-gray-500 text-lg">Nenhum processo em execução encontrado no período selecionado.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
