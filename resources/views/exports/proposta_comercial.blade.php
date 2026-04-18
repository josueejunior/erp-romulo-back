<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposta Comercial - Dispensa Eletrônica</title>
    <style>
        :root {
            --primary-color: #333;
            --border-color: #ccc;
            --bg-light: #f9f9f9;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: var(--primary-color);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: auto;
            border: 1px solid #eee;
            padding: 40px;
            background: #fff;
        }

        header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }

        h1 { font-size: 18px; margin: 0; text-transform: uppercase; }
        h2 { font-size: 14px; margin: 5px 0; }

        .info-section {
            margin-bottom: 20px;
            text-align: justify;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        table, th, td {
            border: 1px solid var(--border-color);
        }

        th {
            background-color: var(--bg-light);
            padding: 10px;
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
        }

        td {
            padding: 8px;
            vertical-align: top;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }

        .obs-list {
            list-style: none;
            padding: 0;
        }

        .obs-list li::before {
            content: "• ";
            font-weight: bold;
        }

        .footer-info {
            margin-top: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .signature-area {
            margin-top: 50px;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 300px;
            margin: 10px auto;
        }

        .tech-spec {
            background: #f4f4f4;
            padding: 15px;
            border-left: 4px solid #333;
            margin-top: 20px;
        }

        /* Estilos para Impressão */
        @media print {
            body { padding: 0; }
            .container { border: none; width: 100%; max-width: 100%; }
            button { display: none; }
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        @if(isset($logo_base64) && $logo_base64)
            <img src="{{ $logo_base64 }}" alt="Logo da Empresa" style="max-width: 150px; max-height: 60px; margin-bottom: 10px;" />
        @elseif(isset($logo_url) && $logo_url)
            <img src="{{ $logo_url }}" alt="Logo da Empresa" style="max-width: 150px; max-height: 60px; margin-bottom: 10px;" />
        @endif
        <h1>Proposta Comercial</h1>
        <h2>Dispensa Eletrônica nº. {{ $processo->numero_modalidade ?? 'N/A' }}/{{ date('Y') }}</h2>
        @php
            $uasg = is_array($processo->orgao->uasg ?? null) ? implode(', ', $processo->orgao->uasg) : (string)($processo->orgao->uasg ?? '');
            $orgaoRazao = is_array($processo->orgao->razao_social ?? null) ? implode(', ', $processo->orgao->razao_social) : (string)($processo->orgao->razao_social ?? 'N/A');
            $orgaoEstado = is_array($processo->orgao->estado ?? null) ? implode(', ', $processo->orgao->estado) : (string)($processo->orgao->estado ?? '');
        @endphp
        <p><strong>UASG: {{ $uasg }}</strong> - {{ $orgaoRazao }}@if($orgaoEstado) - {{ $orgaoEstado }}@endif</p>
    </header>

    <div class="info-section">
        <p>A empresa <strong>{{ $nome_empresa }}{{ $nome_fantasia && trim($nome_fantasia) !== trim($nome_empresa) ? ' (' . $nome_fantasia . ')' : '' }}</strong>, inscrita no CNPJ sob o n° <strong>{{ $cnpj_empresa ?: 'N/A' }}</strong>, sediada na {{ $endereco_completo ?: 'N/A' }}, abaixo assinada por seu representante legal, interessada na participação da presente dispensa eletrônica, propõe a este órgão o fornecimento do objeto deste ato convocatório, de acordo com a presente proposta comercial, nas seguintes condições:</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qtd</th>
                <th>Unid</th>
                <th>Descrição / Marca / Modelo</th>
                <th>V. Unitário</th>
                <th>V. Total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $valorTotalGeral = 0;
            @endphp
            @foreach($itens as $index => $item)
                @php
                    // Buscar orçamento item escolhido (fornecedor_escolhido = true)
                    $orcamentoItemEscolhido = $item->orcamentoItens->firstWhere('fornecedor_escolhido', true);
                    $formacaoPreco = $orcamentoItemEscolhido?->formacaoPreco;
                    
                    // Prioridade: valor arrematado > valor negociado > valor final sessão > preço mínimo formação > valor estimado
                    $valorUnitario = $item->valor_arrematado 
                        ?? $item->valor_negociado 
                        ?? $item->valor_final_sessao 
                        ?? $formacaoPreco?->preco_minimo 
                        ?? $item->valor_estimado 
                        ?? 0;
                    
                    $valorTotalItem = $valorUnitario * $item->quantidade;
                    $valorTotalGeral += $valorTotalItem;
                    
                    $marcaModelo = $orcamentoItemEscolhido?->marca_modelo 
                        ?? $item->marca_modelo_referencia 
                        ?? '';
                    
                    $especificacaoCompleta = $item->especificacao_tecnica;
                    if ($marcaModelo) {
                        $especificacaoCompleta .= ' - ' . $marcaModelo;
                    }
                @endphp
                <tr>
                    <td class="text-center">{{ $item->numero_item ?? ($index + 1) }}</td>
                    <td class="text-center">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                    <td class="text-center">{{ $item->unidade ?? 'UNID' }}</td>
                    <td>
                        <strong>{{ $especificacaoCompleta }}</strong>
                        @if($marcaModelo)
                            <br><small>Marca/Modelo: {{ $marcaModelo }}</small>
                        @endif
                    </td>
                    <td class="text-right">R$ {{ number_format($valorUnitario, 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format($valorTotalItem, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="text-right bold">VALOR TOTAL DA PROPOSTA:</td>
                <td class="text-right bold">R$ {{ number_format($valorTotalGeral, 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    @if($itens->count() > 0)
        @php
            $primeiroItem = $itens->first();
            $orcamentoItemEscolhido = $primeiroItem->orcamentoItens->firstWhere('fornecedor_escolhido', true);
        @endphp
        @if($primeiroItem->especificacao_tecnica || $orcamentoItemEscolhido?->marca_modelo)
            <div class="tech-spec">
                <strong>ESPECIFICAÇÃO TÉCNICA:</strong><br>
                @if($primeiroItem->especificacao_tecnica)
                    {{ $primeiroItem->especificacao_tecnica }}
                @endif
                @if($orcamentoItemEscolhido?->marca_modelo)
                    <br><small>Marca/Modelo: {{ $orcamentoItemEscolhido->marca_modelo }}</small>
                @endif
            </div>
        @endif
    @endif

    <div class="info-section">
        <p class="bold">OBSERVAÇÕES:</p>
        <ul class="obs-list">
            <li>O preço acima inclui todos os custos de impostos, seguros, encargos sociais e demais despesas diretas e indiretas incidentes sobre o objeto da presente dispensa eletrônica.</li>
            <li>Prazo de entrega será conforme condições do Edital/Termo de Referência.</li>
            <li>Validade da Proposta: <strong>{{ $validade_proposta ?? '30 dias corridos' }}</strong>.</li>
            <li>Local de Entrega: Conforme Edital/TR.</li>
            <li>Declaramos estar de acordo com as condições e exigências estabelecidas no Edital/TR.</li>
        </ul>
    </div>

    <div class="footer-info">
        <div>
            <strong>DADOS DO FORNECEDOR:</strong><br>
            {{ $nome_empresa }}{{ $nome_fantasia && trim($nome_fantasia) !== trim($nome_empresa) ? ' (' . $nome_fantasia . ')' : '' }}<br>
            CNPJ: {{ $cnpj_empresa ?: 'N/A' }}<br>
            Endereço: {{ $endereco_completo ?: 'N/A' }}<br>
            @if($email_empresa)
                Email: {{ $email_empresa }}<br>
            @endif
            @if($telefone_empresa)
                Tel: {{ $telefone_empresa }}
            @endif
        </div>
        <div>
            <strong>DADOS BANCÁRIOS:</strong><br>
            Banco: {{ $banco_empresa ?: 'N/A' }}<br>
            Agência: {{ $agencia_empresa ?: 'N/A' }}<br>
            Conta: {{ $conta_empresa ?: 'N/A' }}<br>
            @if($representante_legal)
                Representante: {{ $representante_legal }}@if($cargo_representante) - {{ $cargo_representante }}@endif
            @else
                Representante: N/A
            @endif
        </div>
    </div>

    <div class="signature-area">
        <p>{{ $cidade_empresa ? strtoupper($cidade_empresa) : 'N/A' }}, {{ $data_formatada }}.</p>
        <div class="signature-line"></div>
        <p><strong>{{ $nome_empresa }}</strong><br>
        CNPJ: {{ $cnpj_empresa ?: 'N/A' }}</p>
        @if($representante_legal)
            <p style="margin-top: 10px;">{{ $representante_legal }}@if($cargo_representante) - {{ $cargo_representante }}@endif</p>
        @endif
    </div>
</div>

<div style="text-align: center; margin: 20px;">
    <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer;">Gerar PDF / Imprimir</button>
</div>

</body>
</html>
