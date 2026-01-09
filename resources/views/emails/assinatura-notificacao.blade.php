<x-mail::message>
@if($isNovaAssinatura)
# 🎉 Assinatura Criada com Sucesso!

Olá,

Informamos que uma nova assinatura foi criada para a empresa **{{ $empresa['razao_social'] ?? 'Sua Empresa' }}**.

@else
# 📝 Assinatura Atualizada

Olá,

Informamos que sua assinatura foi atualizada.

@endif

**Detalhes da Assinatura:**

- **Plano:** {{ $plano['nome'] ?? 'N/A' }}
- **Status:** {{ ucfirst($assinatura['status'] ?? 'ativa') }}
- **Valor:** {{ isset($assinatura['valor_pago']) ? 'R$ ' . number_format($assinatura['valor_pago'], 2, ',', '.') : 'Gratuito' }}
- **Método de Pagamento:** {{ ucfirst(str_replace('_', ' ', $assinatura['metodo_pagamento'] ?? 'gratuito')) }}
@if(isset($assinatura['data_inicio']))
- **Data de Início:** {{ \Carbon\Carbon::parse($assinatura['data_inicio'])->format('d/m/Y') }}
@endif
@if(isset($assinatura['data_fim']))
- **Data de Vencimento:** {{ \Carbon\Carbon::parse($assinatura['data_fim'])->format('d/m/Y') }}
@endif
@if(isset($assinatura['dias_grace_period']))
- **Período de Graça:** {{ $assinatura['dias_grace_period'] }} dias
@endif

@if($isNovaAssinatura)
Sua assinatura está ativa e você já pode utilizar todos os recursos do plano contratado.

@if($assinatura['status'] === 'ativa')
Aproveite ao máximo nossa plataforma!
@elseif($assinatura['status'] === 'pendente')
Aguardando confirmação do pagamento. Você receberá uma notificação quando for aprovado.
@endif
@else
As alterações em sua assinatura já estão em vigor.
@endif

Se tiver alguma dúvida, entre em contato com o suporte.

Atenciosamente,<br>
A equipe do Sistema ERP
</x-mail::message>


