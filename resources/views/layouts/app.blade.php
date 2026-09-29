<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="same-origin">
    <title>IdoSell App - @yield('title')</title>
</head>
<body style="font-family: system-ui, -apple-system, sans-serif; background-color: #f3f4f6; color: #1f2937; margin: 0; padding: 40px 20px;">
    <div style="max-width: 800px; margin: 0 auto; background: #ffffff; padding: 32px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <h1 style="margin-top: 0; color: #111827; font-size: 24px;">Integracja IdoSell App SDK</h1>
        <p style="color: #4b5563; font-size: 14px;">Aplikacja testowa korzystająca z pakietu <code>idosell/laravel-app-sdk</code>.</p>

        @yield('content')
    </div>
</body>
</html>
