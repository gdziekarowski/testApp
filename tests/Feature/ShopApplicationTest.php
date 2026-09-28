<?php

declare(strict_types=1);

namespace Tests\Feature;

use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Idosell\LaravelAppSdk\Testing\InteractsWithIdosell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopApplicationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithIdosell;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withIdosellConfig([
            'application_id' => 12345,
            'developer' => 'demo_dev',
            'application_key' => '12345678901234567890123456789012',
            'launch' => [
                'route' => 'app.panel',
            ],
        ]);
    }

    public function test_homepage_shows_fetch_shops_button(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Integracja IdoSell App SDK');
        $response->assertSee('Pokaż sklepy');
    }

    public function test_clicking_button_displays_shops_using_demo_credentials(): void
    {
        Config::set('services.idosell_demo.domain', 'example-shop.iai-shop.com');
        Config::set('services.idosell_demo.api_key', 'demo_api_key_123');

        Http::fake([
            'https://example-shop.iai-shop.com/api/admin/v8/system/shopsData*' => Http::response([
                'results' => [
                    [
                        'id' => 1,
                        'name' => 'Sklep Glowny',
                        'domain' => 'example-shop.iai-shop.com',
                    ],
                    [
                        'id' => 2,
                        'name' => 'Sklep Drugi',
                        'domain' => 'second-shop.iai-shop.com',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->get('/?fetch=1');

        $response->assertStatus(200);
        $response->assertSee('Sklep Glowny');
        $response->assertSee('Sklep Drugi');
        $response->assertSee('example-shop.iai-shop.com');
    }

    public function test_launch_or_stored_license_uses_sdk_license_data(): void
    {
        $license = IdosellLicense::factory()->create([
            'client_id' => 555001,
            'api_url' => 'https://license-shop.iai-shop.com',
            'api_key' => 'secret_license_key',
            'authorization_type' => 'key',
            'active' => true,
        ]);

        Http::fake([
            'https://license-shop.iai-shop.com/api/admin/v8/system/shopsData*' => Http::response([
                'results' => [
                    [
                        'id' => 10,
                        'name' => 'Sklep Licencjonowany',
                        'domain' => 'license-shop.iai-shop.com',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->get('/?client=555001&fetch=1');

        $response->assertStatus(200);
        $response->assertSee('Sklep Licencjonowany');
        $response->assertSee('license-shop.iai-shop.com');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://license-shop.iai-shop.com/api/admin/v8/system/shopsData'
                && $request->hasHeader('X-API-KEY', 'secret_license_key');
        });
    }

    public function test_unauthorized_api_key_error_401(): void
    {
        Config::set('services.idosell_demo.domain', 'example-shop.iai-shop.com');
        Config::set('services.idosell_demo.api_key', 'invalid_key');

        Http::fake([
            'https://example-shop.iai-shop.com/api/admin/v8/system/shopsData*' => Http::response([
                'errors' => ['faultString' => 'Unauthorized key access'],
            ], 401),
        ]);

        $response = $this->get('/?fetch=1');

        $response->assertStatus(200);
        $response->assertSee('Błąd autoryzacji: Odrzucono klucz API sklepu (status HTTP 401).');
    }

    public function test_other_http_error_500(): void
    {
        Config::set('services.idosell_demo.domain', 'example-shop.iai-shop.com');
        Config::set('services.idosell_demo.api_key', 'demo_key');

        Http::fake([
            'https://example-shop.iai-shop.com/api/admin/v8/system/shopsData*' => Http::response([
                'errors' => ['faultString' => 'Internal Server Error'],
            ], 500),
        ]);

        $response = $this->get('/?fetch=1');

        $response->assertStatus(200);
        $response->assertSee('Błąd IdoSell Admin API (status HTTP 500)');
    }

    public function test_connection_exception_handling(): void
    {
        Config::set('services.idosell_demo.domain', 'example-shop.iai-shop.com');
        Config::set('services.idosell_demo.api_key', 'demo_key');

        Http::fake([
            'https://example-shop.iai-shop.com/api/admin/v8/system/shopsData*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Could not resolve host');
            },
        ]);

        $response = $this->get('/?fetch=1');

        $response->assertStatus(200);
        $response->assertSee('Brak połączenia z serwerem IdoSell: Could not resolve host');
    }

    public function test_missing_license_and_missing_demo_credentials(): void
    {
        Config::set('services.idosell_demo.domain', null);
        Config::set('services.idosell_demo.api_key', null);

        $response = $this->get('/?fetch=1');

        $response->assertStatus(200);
        $response->assertSee('Brak aktywnej licencji w systemie oraz brak skonfigurowanych danych demo w .env');
    }
}
