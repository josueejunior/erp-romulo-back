<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resposta ao seu ticket de suporte</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0ea5e9; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #f8fafc; padding: 24px; border: 1px solid #e2e8f0; border-top: none; }
        .resposta { background-color: white; padding: 16px; border-radius: 8px; border-left: 4px solid #0ea5e9; margin: 16px 0; white-space: pre-wrap; }
        .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; text-align: center; color: #64748b; font-size: 12px; }
        .btn { display: inline-block; background-color: #0ea5e9; color: white; padding: 12px 20px; text-decoration: none; border-radius: 8px; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Resposta ao seu ticket de suporte</h1>
    </div>
    <div class="content">
        <p>Olá, <strong>{{ $nomeUsuario }}</strong>.</p>
        <p>O suporte respondeu ao seu ticket <strong>{{ $numero }}</strong> (empresa <strong>{{ $empresaNome }}</strong>).</p>
        <div class="resposta">{{ $mensagem }}</div>
        <p>Acesse o sistema na área de <strong>Suporte</strong> para ver a resposta completa e continuar o atendimento.</p>
        <div class="footer">
            <p>Atenciosamente,<br>Equipe de Suporte</p>
        </div>
    </div>
</body>
</html>
