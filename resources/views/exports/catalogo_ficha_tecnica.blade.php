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
            <strong>Órgão:</strong> {{ $processo->orgao->razao_social }}<br>
            <strong>Setor:</strong> {{ $processo->setor->nome }}<br>
            <strong>Data de Elaboração:</strong> {{ $data_elaboracao }}
        </div>
    </div>

    <div>
        <h2>Objeto</h2>
        <p>{{ $processo->objeto_resumido }}</p>
    </div>

    <div>
        <h2>Especificações Técnicas dos Itens</h2>
        @foreach($itens as $item)
        <div class="item">
            <div class="item-header">
                Item {{ $item->numero_item }} - {{ $item->especificacao_tecnica }}
            </div>
            <div class="item-content">
                <p><strong>Quantidade:</strong> {{ number_format($item->quantidade, 2, ',', '.') }} {{ $item->unidade }}</p>
                
                @if($item->marca_modelo_referencia)
                <p><strong>Marca/Modelo de Referência:</strong> {{ $item->marca_modelo_referencia }}</p>
                @endif

                @if($item->orcamentoEscolhido && $item->orcamentoEscolhido->marca_modelo)
                <p><strong>Marca/Modelo Oferecido:</strong> {{ $item->orcamentoEscolhido->marca_modelo }}</p>
                @endif

                @if($item->orcamentoEscolhido && $item->orcamentoEscolhido->ajustes_especificacao)
                <p><strong>Ajustes na Especificação:</strong> {{ $item->orcamentoEscolhido->ajustes_especificacao }}</p>
                @endif

                <p><strong>Especificação Técnica Completa:</strong></p>
                <p>{{ $item->especificacao_tecnica }}</p>

                @if($item->exige_atestado)
                <p><strong>Exige Atestado de Capacidade Técnica:</strong> Sim</p>
                @if($item->quantidade_atestado_cap_tecnica)
                <p><strong>Quantidade Mínima:</strong> {{ $item->quantidade_atestado_cap_tecnica }}</p>
                @endif
                @endif

                @if($item->observacoes)
                <p><strong>Observações:</strong> {{ $item->observacoes }}</p>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    <div class="footer">
        <p><strong>Empresa:</strong> {{ $processo->nome_empresa }}</p>
        <p>Este documento foi gerado automaticamente pelo sistema em {{ $data_elaboracao }}</p>
    </div>
</body>
</html>

