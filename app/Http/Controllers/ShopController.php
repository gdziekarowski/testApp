<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Panel aplikacji otwierany z panelu IdoSell (webhook `launch` zwraca podpisany URL).
 *
 * Aplikacja działa w iframe panelu IdoSell (cross-site), gdzie przeglądarki nie odsyłają
 * ciasteczka sesji (SameSite=Lax, Safari ITP) — dlatego kontekstu sprzedawcy NIE trzymamy
 * w sesji. Tożsamość (`client`) pochodzi wyłącznie z podpisanego URL: wejście z launch
 * weryfikuje middleware `signed`, a formularz „Pokaż sklepy” wysyła POST na osobny,
 * czasowo podpisany URL (ten sam mechanizm co w onet: podpisany kontekst zamiast cookie).
 */
class ShopController extends Controller
{
    private const ACCESS_DENIED = 'Brak dostępu. Uruchom aplikację z panelu IdoSell.';

    /**
     * Wejście z panelu IdoSell — trasa z middleware `signed`, więc `client` jest zaufany.
     */
    public function index(Request $request): View
    {
        $clientId = $this->signedClientId($request);

        if ($clientId === null) {
            abort(403, self::ACCESS_DENIED);
        }

        return view('welcome', [
            'clientId' => $clientId,
            'fetchUrl' => $this->fetchUrl($clientId),
            'shops' => null,
            'error' => null,
        ]);
    }

    /**
     * „Pokaż sklepy” — `client` bierzemy tylko z podpisanego query stringu, nigdy z body.
     */
    public function fetchShops(Request $request): View
    {
        $clientId = $request->hasValidSignature() ? $this->signedClientId($request) : null;

        if ($clientId === null) {
            return $this->render(null, null, self::ACCESS_DENIED);
        }

        $license = Idosell::license($clientId);

        if (! $license instanceof IdosellLicense || ! $license->active) {
            return $this->render($clientId, null, 'Brak aktywnej licencji w systemie.');
        }

        $result = $this->fetchShopsFromAdminApi($license);

        return $this->render($clientId, $result['shops'], $result['error']);
    }

    private function signedClientId(Request $request): ?int
    {
        $clientId = (int) $request->query('client', 0);

        return $clientId > 0 ? $clientId : null;
    }

    private function fetchUrl(int $clientId): string
    {
        return URL::temporarySignedRoute(
            'app.shops.fetch',
            now()->addMinutes((int) Config::get('idosell.launch.ttl', 30)),
            ['client' => $clientId],
        );
    }

    /**
     * @param  array<int, array{id: int|string, name: string, domain: string}>|null  $shops
     */
    private function render(?int $clientId, ?array $shops, ?string $error): View
    {
        return view('welcome', [
            'clientId' => $clientId,
            'fetchUrl' => $clientId !== null ? $this->fetchUrl($clientId) : null,
            'shops' => $shops,
            'error' => $error,
        ]);
    }

    /**
     * @return array{shops: array<int, array{id: int|string, name: string, domain: string}>|null, error: string|null}
     */
    private function fetchShopsFromAdminApi(IdosellLicense $license): array
    {
        try {
            $client = $license->adminApi(timeout: 5, retries: 0);
            $response = $client->get($client->admin('system/shopsData'));

            return ['shops' => $this->mapShops($response['shop_contact'] ?? []), 'error' => null];
        } catch (ApiException $e) {
            report($e);

            $message = in_array($e->status, [401, 403], true)
                ? 'Błąd autoryzacji: Odrzucono klucz API sklepu (status HTTP '.$e->status.').'
                : 'Błąd IdoSell Admin API'.($e->status ? ' (status HTTP '.$e->status.')' : '');

            return ['shops' => null, 'error' => $this->withDebugDetails($message, $e)];
        } catch (ConnectionException $e) {
            report($e);

            return ['shops' => null, 'error' => $this->withDebugDetails('Brak połączenia z serwerem IdoSell', $e)];
        } catch (Throwable $e) {
            report($e);

            return ['shops' => null, 'error' => $this->withDebugDetails('Wystąpił nieoczekiwany błąd', $e)];
        }
    }

    /**
     * @param  mixed  $rawShops  `shop_contact` z system/shopsData
     * @return array<int, array{id: int|string, name: string, domain: string}>
     */
    private function mapShops(mixed $rawShops): array
    {
        if (! is_array($rawShops)) {
            return [];
        }

        $shops = [];

        foreach ($rawShops as $item) {
            if (! is_array($item) || empty($item['shop_id'])) {
                continue;
            }

            $name = (string) ($item['shop_name'] ?? '');
            $domain = $item['shop_url'] ?? $item['shop_domain'] ?? null;

            $shops[] = [
                'id' => $item['shop_id'],
                'name' => $name !== '' ? $name : '—',
                'domain' => $domain ? (string) $domain : ($name !== '' ? 'https://'.$name : '—'),
            ];
        }

        return $shops;
    }

    private function withDebugDetails(string $message, Throwable $e): string
    {
        return Config::get('app.debug') ? $message.': '.$e->getMessage() : $message;
    }
}
