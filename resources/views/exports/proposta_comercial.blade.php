<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Proposta Comercial - {{ $processo->identificador }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; }
        .info { margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .footer { margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PROPOSTA COMERCIAL</h1>
        <div class="info">
            <strong>Processo:</strong> {{ $processo->identificador }}<br>
            <strong>Órgão:</strong> {{ $processo->orgao->razao_social }}<br>
            <strong>Setor:</strong> {{ $processo->setor->nome }}<br>
            <strong>Data de Elaboração:</strong> {{ $data_elaboracao }}<br>
            <strong>Validade da Proposta:</strong> {{ $validade_proposta }}
        </div>
    </div>

    <div>
        <h2>Objeto</h2>
        <p>{{ $processo->objeto_resumido }}</p>
    </div>

    <div>
        <h2>Itens da Proposta</h2>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Quantidade</th>
                    <th>Unidade</th>
                    <th>Especificação Técnica</th>
                    <th>Marca/Modelo</th>
                    <th>Valor Unitário</th>
                    <th>Valor Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($itens as $item)
                <tr>
                    <td>{{ $item->numero_item }}</td>
                    <td>{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                    <td>{{ $item->unidade }}</td>
                    <td>{{ $item->especificacao_tecnica }}</td>
                    <td>{{ $item->marca_modelo_referencia ?? '-' }}</td>
                    <td>R$ {{ number_format($item->valor_negociado ?? $item->valor_final_sessao ?? $item->valor_estimado ?? 0, 2, ',', '.') }}</td>
                    <td>R$ {{ number_format(($item->valor_negociado ?? $item->valor_final_sessao ?? $item->valor_estimado ?? 0) * $item->quantidade, 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p><strong>Empresa:</strong> {{ $processo->nome_empresa }}</p>
        <p>Este documento foi gerado automaticamente pelo sistema em {{ $data_elaboracao }}</p>
    </div>
</body>
</html>

