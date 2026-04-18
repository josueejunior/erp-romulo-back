@extends('layouts.app')

@section('title', 'Novo Processo')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Novo Processo</h1>
</div>

<div class="bg-white rounded-lg shadow p-6">
    <form method="POST" action="{{ route('processos.store') }}">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Modalidade *</label>
                <select name="modalidade" required class="w-full border rounded px-3 py-2">
                    <option value="dispensa">Dispensa</option>
                    <option value="pregao">Pregão</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Número da Modalidade *</label>
                <input type="text" name="numero_modalidade" required 
                       class="w-full border rounded px-3 py-2" value="{{ old('numero_modalidade') }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Órgão *</label>
                <select name="orgao_id" id="orgao_id" required class="w-full border rounded px-3 py-2">
                    <option value="">Selecione...</option>
                    @foreach($orgaos as $orgao)
                    <option value="{{ $orgao->id }}" {{ old('orgao_id') == $orgao->id ? 'selected' : '' }}>
                        {{ $orgao->razao_social }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Setor *</label>
                <select name="setor_id" id="setor_id" required class="w-full border rounded px-3 py-2">
                    <option value="">Selecione o órgão primeiro</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Número do Processo Administrativo</label>
                <input type="text" name="numero_processo_administrativo" 
                       class="w-full border rounded px-3 py-2" value="{{ old('numero_processo_administrativo') }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data/Hora Sessão Pública *</label>
                <input type="datetime-local" name="data_hora_sessao_publica" required 
                       class="w-full border rounded px-3 py-2" value="{{ old('data_hora_sessao_publica') }}">
            </div>

            <div class="md:col-span-2">
                <label class="flex items-center">
                    <input type="checkbox" name="srp" value="1" {{ old('srp') ? 'checked' : '' }}>
                    <span class="ml-2 text-sm text-gray-700">SRP</span>
                </label>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Objeto Resumido *</label>
                <textarea name="objeto_resumido" required rows="3" 
                          class="w-full border rounded px-3 py-2">{{ old('objeto_resumido') }}</textarea>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Endereço de Entrega</label>
                <input type="text" name="endereco_entrega" 
                       class="w-full border rounded px-3 py-2" value="{{ old('endereco_entrega') }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Forma/Prazo de Entrega</label>
                <textarea name="forma_prazo_entrega" rows="2" 
                          class="w-full border rounded px-3 py-2">{{ old('forma_prazo_entrega') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Prazo de Pagamento</label>
                <textarea name="prazo_pagamento" rows="2" 
                          class="w-full border rounded px-3 py-2">{{ old('prazo_pagamento') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Validade da Proposta</label>
                <textarea name="validade_proposta" rows="2" 
                          class="w-full border rounded px-3 py-2">{{ old('validade_proposta') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Seleção de Fornecedor</label>
                <input type="text" name="tipo_selecao_fornecedor" 
                       class="w-full border rounded px-3 py-2" value="{{ old('tipo_selecao_fornecedor') }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Disputa</label>
                <input type="text" name="tipo_disputa" 
                       class="w-full border rounded px-3 py-2" value="{{ old('tipo_disputa') }}">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Observações</label>
                <textarea name="observacoes" rows="3" 
                          class="w-full border rounded px-3 py-2">{{ old('observacoes') }}</textarea>
            </div>
        </div>

        <div class="mt-6 flex gap-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                Salvar Processo
            </button>
            <a href="{{ route('processos.index') }}" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">
                Cancelar
            </a>
        </div>
    </form>
</div>

<script>
document.getElementById('orgao_id').addEventListener('change', function() {
    const orgaoId = this.value;
    const setorSelect = document.getElementById('setor_id');
    
    setorSelect.innerHTML = '<option value="">Carregando...</option>';
    
    if (!orgaoId) {
        setorSelect.innerHTML = '<option value="">Selecione o órgão primeiro</option>';
        return;
    }
    
    const orgao = @json($orgaos);
    const orgaoSelecionado = orgao.find(o => o.id == orgaoId);
    
    if (orgaoSelecionado && orgaoSelecionado.setors) {
        setorSelect.innerHTML = '<option value="">Selecione...</option>';
        orgaoSelecionado.setors.forEach(setor => {
            setorSelect.innerHTML += `<option value="${setor.id}">${setor.nome}</option>`;
        });
    }
});
</script>
@endsection
