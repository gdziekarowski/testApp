<?php

declare(strict_types=1);

namespace Tests\Feature;

use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Idosell\LaravelAppSdk\Testing\InteractsWithIdosell;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ShopApplicationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithIdosell;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withIdosellConfig([
            'idosell.apps.application_id' => 12345,
            'idosell.apps.developer' => 'demo_dev',
            'idosell.apps.application_key' => str_repeat('1', 32),
            'idosell.launch.route' => 'app.panel',
        ]);
    }

    private function postFetchShops(array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/', array_merge(['_token' => csrf_token()], $data));
    }

    public function test_homepage_shows_fetch_shops_button(): void
    {
        $signedUrl = URL::signedRoute('app.panel');
        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertSee('Integracja IdoSell App SDK');
        $response->assertSee('Pokaż sklepy');
    }

    public function test_clicking_button_displays_shops_using_demo_credentials(): void
    {
        app()['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Config::set('services.idosell_demo.domain', 'example-shop.iai-shop.com');
        Config::set('services.idosell_demo.api_key', 'demo_api_key_123');

        Http::fake([
            'https://example-shop.iai-shop.com/api/admin/v8/system/shopsData*' => Http::response([
                'shop_contact' => [
                    [
                        'shop_id' => 1,
                        'shop_name' => 'example-shop.iai-shop.com',
                    ],
                    [
                        'shop_id' => 2,
                        'shop_name' => 'second-shop.iai-shop.com',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postFetchShops();

        $response->assertStatus(200);
        $response->assertSee('example-shop.iai-shop.com');
        $response->assertSee('second-shop.iai-shop.com');
    }

    public function test_launch_or_stored_license_uses_sdk_license_data(): void
    {
        $license = IdosellLicense::factory()->create([
            'client_id' => 555001,
            'application_id' => 12345,
            'api_url' => 'https://license-shop.iai-shop.com',
            'api_key' => 'secret_license_key',
            'authorization_type' => 'key',
            'active' => true,
        ]);

        Http::fake([
            'https://license-shop.iai-shop.com/api/admin/v8/system/shopsData*' => Http::response([
                'shop_contact' => [
                    [
                        'shop_id' => 10,
                        'shop_name' => 'license-shop.iai-shop.com',
                    ],
                ],
            ], 200),
        ]);

        $signedUrl = URL::signedRoute('app.panel', ['client' => 555001]);
        $this->get($signedUrl)->assertStatus(200);

        $response = $this->postFetchShops();

        $response->assertStatus(200);
        $response->assertSee('license-shop.iai-shop.com');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://license-shop.iai-shop.com/api/admin/v8/system/shopsData'
                && $request->hasHeader('X-API-KEY', 'secret_license_key');
        });
    }

    public function test_unauthorized_api_key_error_401(): void
    {
        app()['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Config::set('services.idosell_demo.domain', 'example-shop.iai-shop.com');
        Config::set('services.idosell_demo.api_key', 'invalid_key');

        Http::fake([
            'https://example-shop.iai-shop.com/api/admin/v8/system/shopsData*' => Http::response([
                'errors' => ['faultString' => 'Unauthorized key access'],
            ], 401),
        ]);

        $response = $this->postFetchShops();

        $response->assertStatus(200);
        $response->assertSee('Błąd autoryzacji: Odrzucono klucz API sklepu (status HTTP 401).');
    }

    public function test_other_http_error_500(): void
    {
        app()['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Config::set('services.idosell_demo.domain', 'example-shop.iai-shop.com');
        Config::set('services.idosell_demo.api_key', 'demo_key');

        Http::fake([
            'https://example-shop.iai-shop.com/api/admin/v8/system/shopsData*' => Http::response([
                'errors' => ['faultString' => 'Internal Server Error'],
            ], 500),
        ]);

        $response = $this->postFetchShops();

        $response->assertStatus(200);
        $response->assertSee('Błąd IdoSell Admin API (status HTTP 500)');
    }

    public function test_connection_exception_handling(): void
    {
        app()['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Config::set('services.idosell_demo.domain', 'example-shop.iai-shop.com');
        Config::set('services.idosell_demo.api_key', 'demo_key');

        Http::fake([
            'https://example-shop.iai-shop.com/api/admin/v8/system/shopsData*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Could not resolve host');
            },
        ]);

        $response = $this->postFetchShops();

        $response->assertStatus(200);
        $response->assertSee('Brak połączenia z serwerem IdoSell');
    }

    public function test_missing_license_and_missing_demo_credentials(): void
    {
        app()['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Config::set('services.idosell_demo.domain', null);
        Config::set('services.idosell_demo.api_key', null);

        $response = $this->postFetchShops();

        $response->assertStatus(200);
        $response->assertSee('Brak aktywnej licencji w systemie oraz brak skonfigurowanych danych demo w .env');
    }

    public function test_unsigned_panel_url_returns_403(): void
    {
        $response = $this->get('/?client=555001');

        $response->assertStatus(403);
    }

    public function test_signed_url_stores_client_id_in_session_and_post_fetches_license_shops(): void
    {
        $license = IdosellLicense::factory()->create([
            'client_id' => 555002,
            'application_id' => 12345,
            'api_url' => 'https://signed-shop.iai-shop.com',
            'api_key' => 'secret_license_key_2',
            'authorization_type' => 'key',
            'active' => true,
        ]);

        Http::fake([
            'https://signed-shop.iai-shop.com/api/admin/v8/system/shopsData*' => Http::response([
                'shop_contact' => [
                    [
                        'shop_id' => 15,
                        'shop_name' => 'signed-shop.iai-shop.com',
                    ],
                ],
            ], 200),
        ]);

        $signedUrl = URL::signedRoute('app.panel', ['client' => 555002]);
        $responseGet = $this->get($signedUrl);

        $responseGet->assertStatus(200);
        $this->assertEquals(555002, session('idosell_client_id'));

        $responsePost = $this->postFetchShops();

        $responsePost->assertStatus(200);
        $responsePost->assertSee('signed-shop.iai-shop.com');
    }

    public function test_missing_client_id_in_session_shows_access_denied_without_calling_api(): void
    {
        Http::fake();

        $response = $this->postFetchShops();

        $response->assertStatus(200);
        $response->assertSee('Brak dostępu. Uruchom aplikację z panelu IdoSell.');
        Http::assertNothingSent();
    }

    public function test_production_environment_disables_demo_fallback(): void
    {
        app()['env'] = 'production';
        $this->withoutMiddleware(ValidateCsrfToken::class);

        Config::set('services.idosell_demo.domain', 'example-shop.iai-shop.com');
        Config::set('services.idosell_demo.api_key', 'demo_api_key_123');

        Http::fake();

        $response = $this->postFetchShops();

        $response->assertStatus(200);
        $response->assertSee('Brak dostępu. Uruchom aplikację z panelu IdoSell.');
        Http::assertNothingSent();
    }

    public function test_api_error_triggers_report_and_shows_general_error_message(): void
    {
        Log::spy();

        $license = IdosellLicense::factory()->create([
            'client_id' => 555003,
            'application_id' => 12345,
            'api_url' => 'https://error-shop.iai-shop.com',
            'api_key' => 'secret_license_key_3',
            'authorization_type' => 'key',
            'active' => true,
        ]);

        Http::fake([
            'https://error-shop.iai-shop.com/api/admin/v8/system/shopsData*' => Http::response([
                'errors' => ['faultString' => 'Internal Server Error'],
            ], 500),
        ]);

        $signedUrl = URL::signedRoute('app.panel', ['client' => 555003]);
        $this->get($signedUrl)->assertStatus(200);

        $response = $this->postFetchShops();

        $response->assertStatus(200);
        $response->assertSee('Błąd IdoSell Admin API (status HTTP 500)');

        Log::shouldHaveReceived('error');
    }
}
