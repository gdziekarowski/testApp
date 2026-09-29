<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Support\LogChannel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ślad każdego żądania do strefy panelu, zapisywany PRZED `idosell.panel`.
 *
 * Dzięki temu w `storage/logs/idosell-*.log` widać także wejścia odrzucone (403) razem
 * z przyczyną, której można się domyślić z pól: brak podpisu, wygasły link (`expires`),
 * brak `client`, brak licencji. Pełnego URL nie logujemy — `signature` to poświadczenie.
 */
class LogPanelRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $expires = $request->query('expires');

        $context = [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $request->route()?->getName(),
            'client' => $request->query('client'),
            'application' => $request->query('application'),
            'has_signature' => $request->query('signature') !== null,
            'signature_valid' => $request->hasValidSignature(),
            'expires_in_s' => is_numeric($expires) ? (int) $expires - now()->getTimestamp() : null,
        ];

        LogChannel::resolve()->debug('Panel ▶ '.$context['method'].' '.$context['path'], $context);

        $response = $next($request);

        $license = Idosell::currentLicense();
        $level = $response->getStatusCode() >= 400 ? 'warning' : 'debug';

        LogChannel::resolve()->{$level}('Panel ◀ '.$context['path'].' → HTTP '.$response->getStatusCode(), [
            'route' => $context['route'],
            'client' => $context['client'],
            'signature_valid' => $context['signature_valid'],
            'license_id' => $license?->getKey(),
            'license_active' => $license?->active,
        ]);

        return $response;
    }
}
