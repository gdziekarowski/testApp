<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Idosell\LaravelAppSdk\Support\LogChannel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Panel aplikacji otwierany z panelu IdoSell.
 *
 * Wszystkie akcje leżą w strefie `idosell.panel`: middleware SDK zweryfikował podpis URL
 * i znalazł aktywną licencję, więc kontroler bierze ją z `Idosell::currentLicense()`
 * (albo przez wstrzyknięcie `IdosellLicense`) i nie sprawdza dostępu sam.
 */
class ShopController extends Controller
{
    private const SHOPS_ENDPOINT = 'system/shopsData';

    public function index(): View
    {
        return $this->render(Idosell::currentLicense());
    }

    /**
     * „Pokaż sklepy”: POST na podpisany URL z `idosell_route()`, bez cookies i tokenu CSRF.
     */
    public function fetchShops(IdosellLicense $license): View
    {
        $result = $this->fetchShopsFromAdminApi($license);

        return $this->render($license, $result['shops'], $result['error']);
    }

    /**
     * Dane instalacji z licencji: do diagnozy, co SDK zapisało z webhooka `new-license`.
     */
    public function installation(IdosellLicense $license): View
    {
        return view('panel.installation', [
            'license' => $license,
            'shops' => $license->shops(),
            'linkTtl' => (int) Config::get('idosell.launch.ttl', 30),
        ]);
    }

    /**
     * @param  array<int, array{id: int|string, name: string, domain: string}>|null  $shops
     */
    private function render(IdosellLicense $license, ?array $shops = null, ?string $error = null): View
    {
        return view('welcome', [
            'clientId' => $license->client_id,
            'shops' => $shops,
            'error' => $error,
        ]);
    }

    /**
     * @return array{shops: array<int, array{id: int|string, name: string, domain: string}>|null, error: string|null}
     */
    private function fetchShopsFromAdminApi(IdosellLicense $license): array
    {
        $context = [
            'client_id' => (int) $license->client_id,
            'domain' => $license->domain(),
            'endpoint' => self::SHOPS_ENDPOINT,
        ];

        try {
            $client = $license->adminApi(timeout: 5, retries: 0);
            $response = $client->get($client->admin(self::SHOPS_ENDPOINT));
            $shops = $this->mapShops($response['shop_contact'] ?? []);

            LogChannel::resolve()->debug('Admin API: pobrano sklepy.', [...$context, 'shops' => count($shops)]);

            return ['shops' => $shops, 'error' => null];
        } catch (ApiException $e) {
            report($e);
            LogChannel::resolve()->warning('Admin API: błąd odpowiedzi.', [...$context, 'status' => $e->status]);

            $message = in_array($e->status, [401, 403], true)
                ? 'Błąd autoryzacji: Odrzucono klucz API sklepu (status HTTP '.$e->status.').'
                : 'Błąd IdoSell Admin API'.($e->status ? ' (status HTTP '.$e->status.')' : '');

            return ['shops' => null, 'error' => $this->withDebugDetails($message, $e)];
        } catch (ConnectionException $e) {
            report($e);
            LogChannel::resolve()->warning('Admin API: brak połączenia.', $context);

            return ['shops' => null, 'error' => $this->withDebugDetails('Brak połączenia z serwerem IdoSell', $e)];
        } catch (Throwable $e) {
            report($e);
            LogChannel::resolve()->error('Admin API: nieoczekiwany błąd.', [...$context, 'exception' => $e::class]);

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
