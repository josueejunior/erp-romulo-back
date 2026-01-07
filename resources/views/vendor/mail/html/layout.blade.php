<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>{{ $title ?? '' }}</title>
<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        line-height: 1.6;
        color: #333;
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
    }
    .button {
        display: inline-block;
        padding: 12px 24px;
        background-color: #2563eb;
        color: #ffffff;
        text-decoration: none;
        border-radius: 6px;
        font-weight: 600;
    }
    .button:hover {
        background-color: #1d4ed8;
    }
</style>
</head>
<body>
{{ $slot }}
</body>
</html>



