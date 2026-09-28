<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Idosell\LaravelAppSdk\Exceptions\ApiException;
use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
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
        $shops = null;
        $error = null;

        if ($request->has('fetch')) {
            $result = $this->fetchShops($clientId);
            $shops = $result['shops'];
            $error = $result['error'];
        }

        return view('welcome', [
            'clientId' => $clientId,
            'shops' => $shops,
            'error' => $error,
        ]);
    }

    /**
     * @return array{shops: array<int, array{id: int|string, name: string, domain?: string}>|null, error: string|null}
     */
    private function fetchShops(?int $clientId): array
    {
        $license = $clientId !== null ? Idosell::license($clientId) : IdosellLicense::query()->where('active', true)->first();

        if ($license !== null && $license->active) {
            return $this->executeApiCall(fn () => $license->adminApi(timeout: 5, retries: 0));
        }

        $demoDomain = (string) Config::get('services.idosell_demo.domain');
        $demoApiKey = (string) Config::get('services.idosell_demo.api_key');

        if (empty($demoDomain) || empty($demoApiKey)) {
            return [
                'shops' => null,
                'error' => 'Brak aktywnej licencji w systemie oraz brak skonfigurowanych danych demo w .env (IDOSELL_DEMO_DOMAIN / IDOSELL_DEMO_API_KEY).',
            ];
        }

        $apiUrl = str_starts_with($demoDomain, 'http') ? $demoDomain : 'https://' . $demoDomain;

        return $this->executeApiCall(fn () => new AdminApiClient(
            apiUrl: $apiUrl,
            apiKey: $demoApiKey,
            authorizationType: 'key',
            timeout: 5,
            retries: 0
        ));
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

            return [
                'shops' => $response['results'] ?? [],
                'error' => null,
            ];
        } catch (ApiException $e) {
            if (in_array($e->status, [401, 403], true)) {
                return [
                    'shops' => null,
                    'error' => 'Błąd autoryzacji: Odrzucono klucz API sklepu (status HTTP ' . $e->status . ').',
                ];
            }

            return [
                'shops' => null,
                'error' => 'Błąd IdoSell Admin API (status HTTP ' . ($e->status ?? 'brak') . '): ' . $e->getMessage(),
            ];
        } catch (ConnectionException $e) {
            return [
                'shops' => null,
                'error' => 'Brak połączenia z serwerem IdoSell: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'shops' => null,
                'error' => 'Wystąpił nieoczekiwany błąd: ' . $e->getMessage(),
            ];
        }
    }
}
