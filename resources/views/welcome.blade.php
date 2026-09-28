<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IdoSell App - Lista Sklepów</title>
</head>
<body style="font-family: system-ui, -apple-system, sans-serif; background-color: #f3f4f6; color: #1f2937; margin: 0; padding: 40px 20px;">
    <div style="max-width: 800px; margin: 0 auto; background: #ffffff; padding: 32px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
        <h1 style="margin-top: 0; color: #111827; font-size: 24px;">Integracja IdoSell App SDK</h1>
        <p style="color: #4b5563; font-size: 14px;">Aplikacja testowa korzystająca z pakietu <code>idosell/laravel-app-sdk</code>.</p>

        @if ($clientId)
            <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 12px 16px; margin-bottom: 24px; border-radius: 4px;">
                <p style="margin: 0; color: #1e40af; font-size: 14px;">Uruchomiono dla klienta ID: <strong>{{ $clientId }}</strong></p>
            </div>
        @endif

        <form method="GET" action="{{ route('app.panel') }}" style="margin-bottom: 24px;">
            @if ($clientId)
                <input type="hidden" name="client" value="{{ $clientId }}">
            @endif
            <input type="hidden" name="fetch" value="1">
            <button id="btn-fetch-shops" type="submit" style="background-color: #2563eb; color: #ffffff; border: none; padding: 10px 20px; font-size: 15px; font-weight: 600; border-radius: 6px; cursor: pointer;">
                Pokaż sklepy
            </button>
        </form>

        @if ($error)
            <div id="error-message" style="background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                <strong>Wystąpił błąd:</strong> {{ $error }}
            </div>
        @endif

        @if ($shops !== null)
            <div id="shops-list-container">
                <h2 style="font-size: 18px; margin-bottom: 16px; color: #111827;">Lista sklepów z panelu:</h2>
                @if (count($shops) > 0)
                    <table style="width: 100%; border-collapse: collapse; text-align: left;">
                        <thead>
                            <tr style="background-color: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                                <th style="padding: 12px; font-size: 13px; color: #6b7280; text-transform: uppercase;">ID</th>
                                <th style="padding: 12px; font-size: 13px; color: #6b7280; text-transform: uppercase;">Nazwa Sklepu</th>
                                <th style="padding: 12px; font-size: 13px; color: #6b7280; text-transform: uppercase;">Adres / Domena</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($shops as $shop)
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td style="padding: 12px; font-weight: 600;">{{ $shop['id'] ?? '—' }}</td>
                                    <td style="padding: 12px;">{{ $shop['name'] ?? ($shop['shop_name'] ?? '—') }}</td>
                                    <td style="padding: 12px; color: #4b5563;">{{ $shop['domain'] ?? ($shop['shop_domain'] ?? '—') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p style="color: #6b7280; font-style: italic;">Brak danych o sklepach do wyświetlenia.</p>
                @endif
            </div>
        @endif
    </div>
</body>
</html>
