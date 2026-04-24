<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Catálogo/Ficha Técnica - {{ $processo->identificador }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; }
        .info { margin: 10px 0; }
        .item { margin: 30px 0; page-break-inside: avoid; }
        .item-header { background-color: #f2f2f2; padding: 10px; font-weight: bold; }
        .item-content { padding: 10px; border: 1px solid #ddd; }
        .footer { margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CATÁLOGO / FICHA TÉCNICA</h1>
        <div class="info">
            <strong>Processo:</strong> {{ $processo->identificador }}<br>
            <strong>Órgão:</strong> {{ $processo->orgao->razao_social ?? 'Não informado' }}<br>
            <strong>Setor:</strong> {{ $processo->setor->nome ?? 'Não informado' }}<br>
            <strong>Data de Elaboração:</strong> {{ $data_elaboracao }}
        </div>
    </div>

    <div>
        <h2>Objeto</h2>
        <p>{{ $processo->objeto_resumido }}</p>
    </div>

    <div>
        <h2>Especificações Técnicas dos Itens</h2>
        @foreach(($itens_catalogo ?? $itens) as $item)
        @php
            $orcamentosItem = data_get($item, 'orcamentos', collect());
            $orcamentoEscolhido = collect($orcamentosItem)->firstWhere('fornecedor_escolhido', true);
        @endphp
        <div class="item">
            <div class="item-header">
                Item {{ data_get($item, 'numero_item') }} - {{ data_get($item, 'especificacao_tecnica') }}
            </div>
            <div class="item-content">
                <p><strong>Quantidade:</strong> {{ number_format((float)data_get($item, 'quantidade', 0), 2, ',', '.') }} {{ data_get($item, 'unidade') }}</p>
                
                @if(data_get($item, 'marca_modelo_referencia'))
                <p><strong>Marca/Modelo de Referência:</strong> {{ data_get($item, 'marca_modelo_referencia') }}</p>
                @endif

                @if($orcamentoEscolhido && $orcamentoEscolhido->marca_modelo)
                <p><strong>Marca/Modelo Oferecido:</strong> {{ $orcamentoEscolhido->marca_modelo }}</p>
                @endif

                @if($orcamentoEscolhido && $orcamentoEscolhido->ajustes_especificacao)
                <p><strong>Ajustes na Especificação:</strong> {{ $orcamentoEscolhido->ajustes_especificacao }}</p>
                @endif

                <p><strong>Especificação Técnica Completa:</strong></p>
                <p>{{ data_get($item, 'especificacao_tecnica') }}</p>

                @if(data_get($item, 'especificacao_detalhada'))
                <p><strong>Especificação Detalhada:</strong></p>
                <p>{!! nl2br(e(data_get($item, 'especificacao_detalhada'))) !!}</p>
                @endif

                @php
                    $imagens = collect(data_get($item, 'imagens', []))->filter()->values();
                @endphp
                @if($imagens->isNotEmpty())
                <p><strong>Imagens Anexadas:</strong></p>
                <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                    @foreach($imagens as $imagem)
                    <img src="{{ $imagem }}" alt="Imagem do item {{ data_get($item, 'numero_item') }}" style="max-width: 220px; max-height: 220px; border: 1px solid #ddd; border-radius: 6px; object-fit: contain; padding: 6px;" />
                    @endforeach
                </div>
                @endif

                @if(data_get($item, 'exige_atestado'))
                <p><strong>Exige Atestado de Capacidade Técnica:</strong> Sim</p>
                @if(data_get($item, 'quantidade_atestado_cap_tecnica'))
                <p><strong>Quantidade Mínima:</strong> {{ data_get($item, 'quantidade_atestado_cap_tecnica') }}</p>
                @endif
                @endif

                @if(data_get($item, 'observacoes'))
                <p><strong>Observações:</strong> {{ data_get($item, 'observacoes') }}</p>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    @if(!empty($observacoes_gerais))
    <div>
        <h2>Observações Gerais</h2>
        <p>{!! nl2br(e($observacoes_gerais)) !!}</p>
    </div>
    @endif

    <div class="footer">
        <p><strong>Empresa:</strong> {{ $nome_empresa ?? 'Empresa não identificada' }}</p>
        <p>Este documento foi gerado automaticamente pelo sistema em {{ $data_elaboracao }}</p>
    </div>
</body>
</html>

