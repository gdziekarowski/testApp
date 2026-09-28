<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Services\AdminApiClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $clientId = $request->query('client') ? (int) $request->query('client') : null;

        if ($clientId !== null) {
            session()->regenerate();
            session()->put('idosell_client_id', $clientId);
        }

        return view('welcome', [
            'clientId' => $clientId ?? session()->get('idosell_client_id'),
            'shops' => null,
            'error' => null,
        ]);
    }

    public function fetchShops(Request $request): View
    {
        $clientId = session()->get('idosell_client_id');
        $shops = null;
        $error = null;

        if ($clientId !== null) {
            $license = Idosell::license((int) $clientId);
            $appId = Config::get('idosell.apps.application_id');

            if ($license === null || ! $license->active || ($appId !== null && (int) $license->application_id !== (int) $appId)) {
                $error = 'Brak aktywnej licencji w systemie.';
            } else {
                $result = $this->executeApiCall(fn () => $license->adminApi(timeout: 5, retries: 0));
                $shops = $result['shops'];
                $error = $result['error'];
            }
        } else {
            if (! app()->isLocal()) {
                $error = 'Brak dostępu. Uruchom aplikację z panelu IdoSell.';
            } else {
                $demoDomain = (string) Config::get('services.idosell_demo.domain');
                $demoApiKey = (string) Config::get('services.idosell_demo.api_key');

                if (empty($demoDomain) || empty($demoApiKey)) {
                    $error = 'Brak aktywnej licencji w systemie oraz brak skonfigurowanych danych demo w .env (IDOSELL_DEMO_DOMAIN / IDOSELL_DEMO_API_KEY).';
                } else {
                    $apiUrl = str_starts_with($demoDomain, 'http') ? $demoDomain : 'https://' . $demoDomain;

                    $result = $this->executeApiCall(fn () => new AdminApiClient(
                        apiUrl: $apiUrl,
                        apiKey: $demoApiKey,
                        authorizationType: 'key',
                        timeout: 5,
                        retries: 0
                    ));
                    $shops = $result['shops'];
                    $error = $result['error'];
                }
            }
        }

        return view('welcome', [
            'clientId' => $clientId,
            'shops' => $shops,
            'error' => $error,
        ]);
    }

    /**
     * @param callable(): AdminApiClient $clientResolver
     * @return array{shops: array<int, array{id: int|string, name: string, domain?: string}>|null, error: string|null}
     */
    private function executeApiCall(callable $clientResolver): array
    {
        try {
            $client = $clientResolver();
            $response = $client->get($client->admin('system/shopsData'));

            $rawShops = $response['shop_contact'] ?? [];
            $shops = [];
            foreach ($rawShops as $item) {
                if (empty($item['shop_id'])) {
                    continue;
                }

                $domain = $item['shop_url'] ?? $item['shop_domain'] ?? (
                    str_starts_with($item['shop_name'] ?? '', 'http')
                        ? $item['shop_name']
                        : 'https://' . ($item['shop_name'] ?? '')
                );

                $shops[] = [
                    'id' => $item['shop_id'],
                    'name' => $item['shop_name'] ?? '—',
                    'domain' => $domain,
                ];
            }

            return [
                'shops' => $shops,
                'error' => null,
            ];
        } catch (ApiException $e) {
            report($e);
            $debug = (bool) Config::get('app.debug');

            if (in_array($e->status, [401, 403], true)) {
                $msg = 'Błąd autoryzacji: Odrzucono klucz API sklepu (status HTTP ' . $e->status . ').';
                return [
                    'shops' => null,
                    'error' => $debug ? $msg . ': ' . $e->getMessage() : $msg,
                ];
            }

            $statusStr = $e->status ? ' (status HTTP ' . $e->status . ')' : '';
            $msg = 'Błąd IdoSell Admin API' . $statusStr;

            return [
                'shops' => null,
                'error' => $debug ? $msg . ': ' . $e->getMessage() : $msg,
            ];
        } catch (ConnectionException $e) {
            report($e);
            $debug = (bool) Config::get('app.debug');
            $msg = 'Brak połączenia z serwerem IdoSell';

            return [
                'shops' => null,
                'error' => $debug ? $msg . ': ' . $e->getMessage() : $msg,
            ];
        } catch (\Throwable $e) {
            report($e);
            $debug = (bool) Config::get('app.debug');
            $msg = 'Wystąpił nieoczekiwany błąd';

            return [
                'shops' => null,
                'error' => $debug ? $msg . ': ' . $e->getMessage() : $msg,
            ];
        }
    }
}
