@extends('layouts.app')

@section('title', 'Selecionar Empresa')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-8 text-center">
        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
            <x-heroicon-o-building-office class="w-6 h-6 text-white" />
        </div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Selecione uma Empresa</h1>
        <p class="text-gray-600">Escolha a empresa que deseja gerenciar</p>
    </div>

    <div class="card p-8">
        <form method="POST" action="{{ route('empresas.definir') }}">
            @csrf
            <div class="space-y-3">
                @foreach($empresas as $empresa)
                <label class="flex items-center p-5 border-2 rounded-lg cursor-pointer transition-all hover:border-blue-400 hover:bg-blue-50 {{ auth()->user()->empresa_ativa_id == $empresa->id ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }}">
                    <input type="radio" name="empresa_id" value="{{ $empresa->id }}" 
                           class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300" 
                           {{ auth()->user()->empresa_ativa_id == $empresa->id ? 'checked' : '' }}>
                    <div class="ml-4 flex-1">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ $empresa->razao_social }}</h3>
                                @if($empresa->cnpj)
                                <p class="text-sm text-gray-600 mt-1">{{ $empresa->cnpj }}</p>
                                @endif
                                @if($empresa->cidade || $empresa->estado)
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $empresa->cidade }}{{ $empresa->cidade && $empresa->estado ? '/' : '' }}{{ $empresa->estado }}
                                </p>
                                @endif
                            </div>
                            @if(auth()->user()->empresa_ativa_id == $empresa->id)
                            <span class="px-3 py-1 text-xs font-semibold bg-blue-600 text-white rounded-full">
                                Ativa
                            </span>
                            @endif
                        </div>
                    </div>
                </label>
                @endforeach
            </div>

            <div class="mt-8 flex justify-center pt-6 border-t border-gray-200">
                <button type="submit" class="btn-primary text-white px-8 py-3 rounded-lg font-semibold flex items-center gap-2 shadow-lg">
                    <x-heroicon-o-check class="w-5 h-5" />
                    Selecionar Empresa
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
