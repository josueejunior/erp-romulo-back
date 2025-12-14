@extends('layouts.app')

@section('title', 'Processo: ' . $processo->numero_modalidade)

@section('content')
<div class="mb-8 flex justify-between items-center">
    <div class="flex items-center gap-4">
        <a href="{{ route('processos.index') }}" class="text-gray-600 hover:text-gray-900">
            <x-heroicon-o-arrow-left class="w-6 h-6" />
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ $processo->numero_modalidade }}</h1>
            <p class="text-gray-600 mt-1">{{ $processo->objeto_resumido }}</p>
        </div>
    </div>
    <div class="flex gap-3">
        @if(!$processo->isEmExecucao())
        <a href="{{ route('processos.edit', $processo) }}" 
           class="btn-primary text-white px-6 py-3 rounded-lg font-medium flex items-center gap-2 shadow-lg">
            <x-heroicon-o-pencil class="w-5 h-5" />
            Editar
        </a>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="card p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Status</p>
                @php
                    $statusColors = [
                        'participacao' => 'text-blue-600',
                        'julgamento_habilitacao' => 'text-yellow-600',
                        'execucao' => 'text-green-600',
                        'vencido' => 'text-purple-600',
                        'perdido' => 'text-red-600',
                        'arquivado' => 'text-gray-600',
                    ];
                    $color = $statusColors[$processo->status] ?? 'text-gray-600';
                @endphp
                <p class="text-xl font-bold {{ $color }}">{{ ucfirst(str_replace('_', ' ', $processo->status)) }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <x-heroicon-o-check-circle class="w-5 h-5 text-blue-600" />
            </div>
        </div>
    </div>
    <div class="card p-6 border-l-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Órgão</p>
                <p class="text-lg font-bold text-gray-900">{{ $processo->orgao->razao_social }}</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <x-heroicon-o-building-office class="w-5 h-5 text-green-600" />
            </div>
        </div>
    </div>
    <div class="card p-6 border-l-4 border-purple-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 mb-1">Sessão Pública</p>
                <p class="text-lg font-bold text-gray-900">{{ $processo->data_hora_sessao_publica->format('d/m/Y') }}</p>
                <p class="text-sm text-gray-600">{{ $processo->data_hora_sessao_publica->format('H:i') }}</p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <x-heroicon-o-calendar class="w-5 h-5 text-purple-600" />
            </div>
        </div>
    </div>
</div>

<div class="card p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-3">
        <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600" />
        Informações do Processo
    </h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="pb-4 border-b border-gray-100">
            <p class="text-sm font-medium text-gray-500 mb-1">Modalidade</p>
            <p class="text-base font-semibold text-gray-900">{{ ucfirst($processo->modalidade) }}</p>
        </div>
        <div class="pb-4 border-b border-gray-100">
            <p class="text-sm font-medium text-gray-500 mb-1">Setor</p>
            <p class="text-base font-semibold text-gray-900">{{ $processo->setor->nome }}</p>
        </div>
        @if($processo->numero_processo_administrativo)
        <div class="pb-4 border-b border-gray-100">
            <p class="text-sm font-medium text-gray-500 mb-1">Processo Administrativo</p>
            <p class="text-base font-semibold text-gray-900">{{ $processo->numero_processo_administrativo }}</p>
        </div>
        @endif
        @if($processo->srp)
        <div class="pb-4 border-b border-gray-100">
            <p class="text-sm font-medium text-gray-500 mb-1">SRP</p>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                Sim
            </span>
        </div>
        @endif
    </div>
</div>

<div class="card p-6 mb-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-900 flex items-center gap-3">
            <x-heroicon-o-clipboard-document class="w-5 h-5 text-blue-600" />
            Itens do Processo
        </h2>
        @if(!$processo->isEmExecucao())
        <a href="{{ route('processo-itens.create', $processo) }}" 
           class="btn-primary text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2 shadow-lg">
            <x-heroicon-o-plus class="w-5 h-5" />
            Novo Item
        </a>
        @endif
    </div>
    @if($processo->itens->count() > 0)
    <div class="space-y-4">
        @foreach($processo->itens as $item)
        <div class="border border-gray-200 rounded-lg p-5 hover:border-blue-300 hover:bg-blue-50/50 transition-all">
            <div class="flex justify-between items-start mb-3">
                <h3 class="text-lg font-bold text-gray-900">Item {{ $item->numero_item }}</h3>
                <div class="flex items-center gap-3">
                    @php
                        $itemStatusColors = [
                            'aceito' => 'bg-green-100 text-green-800',
                            'aceito_habilitado' => 'bg-blue-100 text-blue-800',
                            'desclassificado' => 'bg-red-100 text-red-800',
                            'inabilitado' => 'bg-orange-100 text-orange-800',
                            'pendente' => 'bg-gray-100 text-gray-800',
                        ];
                        $itemColor = $itemStatusColors[$item->status_item] ?? 'bg-gray-100 text-gray-800';
                    @endphp
                    <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $itemColor }}">
                        {{ ucfirst(str_replace('_', ' ', $item->status_item)) }}
                    </span>
                    @if(!$processo->isEmExecucao())
                    <div class="flex items-center gap-2">
                        <a href="{{ route('processo-itens.edit', [$processo, $item]) }}" 
                           class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Editar</a>
                        <form method="POST" action="{{ route('processo-itens.destroy', [$processo, $item]) }}" class="inline" 
                              onsubmit="return confirm('Tem certeza que deseja excluir este item?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900 text-sm font-medium">Excluir</button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
            <p class="text-sm text-gray-700 mb-4 leading-relaxed">{{ $item->especificacao_tecnica }}</p>
            @if($item->marca_modelo_referencia)
            <p class="text-xs text-gray-500 mb-2"><strong>Marca/Modelo:</strong> {{ $item->marca_modelo_referencia }}</p>
            @endif
            @if($item->exige_atestado)
            <p class="text-xs text-yellow-600 mb-2">
                <x-heroicon-o-exclamation-triangle class="w-4 h-4 inline" />
                <strong>Exige Atestado de Capacidade Técnica</strong>
                @if($item->quantidade_minima_atestado)
                    - Quantidade mínima: {{ $item->quantidade_minima_atestado }}
                @endif
            </p>
            @endif
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg p-3 border border-gray-100">
                    <p class="text-xs font-medium text-gray-500 mb-1">Quantidade</p>
                    <p class="text-sm font-bold text-gray-900">{{ $item->quantidade }} {{ $item->unidade }}</p>
                </div>
                @if($item->valor_estimado)
                <div class="bg-white rounded-lg p-3 border border-gray-100">
                    <p class="text-xs font-medium text-gray-500 mb-1">Valor Estimado</p>
                    <p class="text-sm font-bold text-gray-900">R$ {{ number_format($item->valor_estimado, 2, ',', '.') }}</p>
                </div>
                @endif
                @if($item->valor_final_sessao)
                <div class="bg-white rounded-lg p-3 border border-gray-100">
                    <p class="text-xs font-medium text-gray-500 mb-1">Valor Final</p>
                    <p class="text-sm font-bold text-green-600">R$ {{ number_format($item->valor_final_sessao, 2, ',', '.') }}</p>
                </div>
                @endif
                @if($item->formacoesPreco->count() > 0)
                <div class="bg-white rounded-lg p-3 border border-blue-200 bg-blue-50">
                    <p class="text-xs font-medium text-gray-500 mb-1">Preço Mínimo</p>
                    <p class="text-sm font-bold text-blue-600">R$ {{ number_format($item->formacoesPreco->first()->preco_minimo, 2, ',', '.') }}</p>
                </div>
                @endif
            </div>
            
            @if($item->orcamentos->count() > 0)
            <div class="mt-4 pt-4 border-t border-gray-200">
                <div class="flex justify-between items-center mb-3">
                    <h4 class="text-sm font-semibold text-gray-700">Orçamentos ({{ $item->orcamentos->count() }})</h4>
                    @if(!$processo->isEmExecucao())
                    <a href="{{ route('orcamentos.create', [$processo, $item]) }}" 
                       class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        + Novo Orçamento
                    </a>
                    @endif
                </div>
                <div class="space-y-2">
                    @foreach($item->orcamentos as $orcamento)
                    <div class="bg-gray-50 rounded-lg p-3 border {{ $orcamento->fornecedor_escolhido ? 'border-green-300 bg-green-50' : 'border-gray-200' }}">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-sm font-semibold text-gray-900">{{ $orcamento->fornecedor->razao_social }}</span>
                                    @if($orcamento->fornecedor_escolhido)
                                    <span class="px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-800 rounded-full">Escolhido</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-600 space-y-1">
                                    <p><strong>Custo:</strong> R$ {{ number_format($orcamento->custo_produto, 2, ',', '.') }}</p>
                                    @if($orcamento->frete > 0 && !$orcamento->frete_incluido)
                                    <p><strong>Frete:</strong> R$ {{ number_format($orcamento->frete, 2, ',', '.') }}</p>
                                    @elseif($orcamento->frete_incluido)
                                    <p class="text-green-600"><strong>Frete:</strong> Incluído</p>
                                    @endif
                                    <p><strong>Total:</strong> R$ {{ number_format($orcamento->custo_total, 2, ',', '.') }}</p>
                                    @if($orcamento->marca_modelo)
                                    <p><strong>Marca/Modelo:</strong> {{ $orcamento->marca_modelo }}</p>
                                    @endif
                                    @if($orcamento->transportadora)
                                    <p><strong>Transportadora:</strong> {{ $orcamento->transportadora->razao_social }}</p>
                                    @endif
                                    @if($orcamento->formacaoPreco)
                                    <p class="mt-2">
                                        <strong class="text-blue-600">Preço Mínimo:</strong> 
                                        <span class="font-bold text-blue-600">R$ {{ number_format($orcamento->formacaoPreco->preco_minimo, 2, ',', '.') }}</span>
                                    </p>
                                    @endif
                                </div>
                            </div>
                            @if(!$processo->isEmExecucao())
                            <div class="flex flex-col items-end gap-2">
                                <div class="flex items-center gap-2">
                                    @if($orcamento->formacaoPreco)
                                    <a href="{{ route('formacao-precos.edit', [$processo, $item, $orcamento, $orcamento->formacaoPreco]) }}" 
                                       class="text-xs text-blue-600 hover:text-blue-900">Formação Preço</a>
                                    @else
                                    <a href="{{ route('formacao-precos.create', [$processo, $item, $orcamento]) }}" 
                                       class="text-xs text-blue-600 hover:text-blue-900">Formar Preço</a>
                                    @endif
                                    <a href="{{ route('orcamentos.edit', [$processo, $item, $orcamento]) }}" 
                                       class="text-xs text-indigo-600 hover:text-indigo-900">Editar</a>
                                    <form method="POST" action="{{ route('orcamentos.destroy', [$processo, $item, $orcamento]) }}" class="inline" 
                                          onsubmit="return confirm('Tem certeza que deseja excluir este orçamento?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:text-red-900">Excluir</button>
                                    </form>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @else
            @if(!$processo->isEmExecucao())
            <div class="mt-4 pt-4 border-t border-gray-200">
                <a href="{{ route('orcamentos.create', [$processo, $item]) }}" 
                   class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    + Adicionar primeiro orçamento
                </a>
            </div>
            @endif
            @endif
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-12">
        <x-heroicon-o-clipboard-document class="w-8 h-8 text-gray-400 mx-auto mb-4" />
        <p class="text-gray-500 text-lg">Nenhum item cadastrado ainda.</p>
    </div>
    @endif
</div>

@if($processo->isEmExecucao())
<div class="card p-6 mb-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-gray-900 flex items-center gap-3">
            <x-heroicon-o-receipt-percent class="w-5 h-5 text-purple-600" />
            Notas Fiscais
        </h2>
        <a href="{{ route('notas-fiscais.create', $processo) }}" 
           class="btn-primary text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2 shadow-lg">
            <x-heroicon-o-plus class="w-5 h-5" />
            Nova Nota Fiscal
        </a>
    </div>
    @if($processo->notasFiscais->count() > 0)
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Número</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data Emissão</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fornecedor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($processo->notasFiscais as $nf)
                <tr class="table-row transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $nf->tipo == 'entrada' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                            {{ $nf->tipo == 'entrada' ? 'Entrada' : 'Saída' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $nf->numero }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $nf->data_emissao->format('d/m/Y') }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $nf->fornecedor->razao_social ?? '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold {{ $nf->tipo == 'entrada' ? 'text-red-600' : 'text-green-600' }}">
                        R$ {{ number_format($nf->valor, 2, ',', '.') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                            {{ $nf->situacao == 'paga' ? 'bg-green-100 text-green-800' : 
                               ($nf->situacao == 'cancelada' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                            {{ ucfirst($nf->situacao) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('notas-fiscais.show', [$processo, $nf]) }}" class="text-blue-600 hover:text-blue-900">Ver</a>
                            <a href="{{ route('notas-fiscais.edit', [$processo, $nf]) }}" class="text-indigo-600 hover:text-indigo-900">Editar</a>
                            <form method="POST" action="{{ route('notas-fiscais.destroy', [$processo, $nf]) }}" class="inline" 
                                  onsubmit="return confirm('Tem certeza que deseja excluir esta nota fiscal?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-600 hover:text-red-900">Excluir</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <p class="text-gray-500 text-center py-4">Nenhuma nota fiscal cadastrada.</p>
    @endif
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="card p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <x-heroicon-o-document-text class="w-5 h-5 text-green-600" />
                Contratos
            </h2>
            <a href="{{ route('contratos.create', $processo) }}" 
               class="text-blue-600 hover:text-blue-900 text-sm font-medium">+ Novo</a>
        </div>
        @if($processo->contratos->count() > 0)
        <div class="space-y-3">
            @foreach($processo->contratos as $contrato)
            <div class="border border-gray-200 rounded-lg p-3 bg-gray-50">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="text-sm font-bold text-gray-900">{{ $contrato->numero }}</h3>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full 
                        {{ $contrato->situacao == 'vigente' ? 'bg-green-100 text-green-800' : 
                           ($contrato->situacao == 'encerrado' ? 'bg-gray-100 text-gray-800' : 'bg-red-100 text-red-800') }}">
                        {{ ucfirst($contrato->situacao) }}
                    </span>
                </div>
                <p class="text-xs text-gray-600"><strong>Valor:</strong> R$ {{ number_format($contrato->valor_total, 2, ',', '.') }}</p>
                <p class="text-xs text-gray-600"><strong>Saldo:</strong> R$ {{ number_format($contrato->saldo, 2, ',', '.') }}</p>
                <div class="mt-2 flex gap-2">
                    <a href="{{ route('contratos.show', [$processo, $contrato]) }}" class="text-xs text-blue-600 hover:text-blue-900">Ver</a>
                    <a href="{{ route('contratos.edit', [$processo, $contrato]) }}" class="text-xs text-indigo-600 hover:text-indigo-900">Editar</a>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-gray-500 text-sm text-center py-2">Nenhum contrato</p>
        @endif
    </div>

    <div class="card p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <x-heroicon-o-document-check class="w-5 h-5 text-blue-600" />
                Autorizações (AF)
            </h2>
            <a href="{{ route('autorizacoes-fornecimento.create', $processo) }}" 
               class="text-blue-600 hover:text-blue-900 text-sm font-medium">+ Nova</a>
        </div>
        @if($processo->autorizacoesFornecimento->count() > 0)
        <div class="space-y-3">
            @foreach($processo->autorizacoesFornecimento as $af)
            <div class="border border-gray-200 rounded-lg p-3 bg-gray-50">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="text-sm font-bold text-gray-900">{{ $af->numero }}</h3>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full 
                        {{ $af->situacao == 'concluida' ? 'bg-green-100 text-green-800' : 
                           ($af->situacao == 'atendendo' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') }}">
                        {{ ucfirst(str_replace('_', ' ', $af->situacao)) }}
                    </span>
                </div>
                <p class="text-xs text-gray-600"><strong>Valor:</strong> R$ {{ number_format($af->valor, 2, ',', '.') }}</p>
                <p class="text-xs text-gray-600"><strong>Saldo:</strong> R$ {{ number_format($af->saldo, 2, ',', '.') }}</p>
                <div class="mt-2 flex gap-2">
                    <a href="{{ route('autorizacoes-fornecimento.show', [$processo, $af]) }}" class="text-xs text-blue-600 hover:text-blue-900">Ver</a>
                    <a href="{{ route('autorizacoes-fornecimento.edit', [$processo, $af]) }}" class="text-xs text-indigo-600 hover:text-indigo-900">Editar</a>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-gray-500 text-sm text-center py-2">Nenhuma AF</p>
        @endif
    </div>

    <div class="card p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <x-heroicon-o-banknotes class="w-5 h-5 text-purple-600" />
                Empenhos
            </h2>
            <a href="{{ route('empenhos.create', $processo) }}" class="text-blue-600 hover:text-blue-900 text-sm font-medium">+ Novo</a>
        </div>
        @if($processo->empenhos->count() > 0)
        <div class="space-y-3">
            @foreach($processo->empenhos->take(5) as $empenho)
            <div class="border border-gray-200 rounded-lg p-3 bg-gray-50">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="text-sm font-bold text-gray-900">{{ $empenho->numero }}</h3>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $empenho->concluido ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                        {{ $empenho->concluido ? 'Concluído' : 'Pendente' }}
                    </span>
                </div>
                <p class="text-xs text-gray-600"><strong>Valor:</strong> R$ {{ number_format($empenho->valor, 2, ',', '.') }}</p>
                <p class="text-xs text-gray-600"><strong>Data:</strong> {{ $empenho->data->format('d/m/Y') }}</p>
                <div class="mt-2 flex gap-2">
                    <a href="{{ route('empenhos.show', [$processo, $empenho]) }}" class="text-xs text-blue-600 hover:text-blue-900">Ver</a>
                    <a href="{{ route('empenhos.edit', [$processo, $empenho]) }}" class="text-xs text-indigo-600 hover:text-indigo-900">Editar</a>
                </div>
            </div>
            @endforeach
            @if($processo->empenhos->count() > 5)
            <p class="text-xs text-gray-500 text-center">+ {{ $processo->empenhos->count() - 5 }} mais</p>
            @endif
        </div>
        @else
        <p class="text-gray-500 text-sm text-center py-2">Nenhum empenho</p>
        @endif
    </div>
</div>
@endif

@if(!$processo->isEmExecucao())
<div class="flex gap-4 flex-wrap">
    @if($processo->data_hora_sessao_publica->isPast() && $processo->status === 'participacao')
    <a href="{{ route('disputas.edit', $processo) }}" 
       class="bg-gradient-to-r from-purple-600 to-purple-700 text-white px-6 py-3 rounded-lg hover:from-purple-700 hover:to-purple-800 transition-all font-semibold flex items-center gap-2 shadow-lg">
        <x-heroicon-o-trophy class="w-5 h-5" />
        Registrar Disputa
    </a>
    @endif
    @if(in_array($processo->status, ['participacao', 'julgamento_habilitacao']))
    <a href="{{ route('julgamentos.edit', $processo) }}" 
       class="bg-gradient-to-r from-yellow-600 to-yellow-700 text-white px-6 py-3 rounded-lg hover:from-yellow-700 hover:to-yellow-800 transition-all font-semibold flex items-center gap-2 shadow-lg">
        <x-heroicon-o-clipboard-document-check class="w-5 h-5" />
        Julgamento e Habilitação
    </a>
    @endif
    <form method="POST" action="{{ route('processos.marcar-vencido', $processo) }}" class="inline">
        @csrf
        <button type="submit" class="bg-gradient-to-r from-green-600 to-green-700 text-white px-6 py-3 rounded-lg hover:from-green-700 hover:to-green-800 transition-all font-semibold flex items-center gap-2 shadow-lg"
                onclick="return confirm('Tem certeza? O processo será travado e movido para execução.')">
            <x-heroicon-o-check-circle class="w-5 h-5" />
            Marcar como Vencido
        </button>
    </form>
    <form method="POST" action="{{ route('processos.marcar-perdido', $processo) }}" class="inline">
        @csrf
        <button type="submit" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-3 rounded-lg hover:from-red-700 hover:to-red-800 transition-all font-semibold flex items-center gap-2 shadow-lg"
                onclick="return confirm('Tem certeza que deseja marcar como perdido?')">
            <x-heroicon-o-x-mark class="w-5 h-5" />
            Marcar como Perdido
        </button>
    </form>
</div>
@endif
@endsection
