<?php

declare(strict_types=1);

namespace Tests\Feature;

use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Idosell\LaravelAppSdk\Testing\InteractsWithIdosell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ShopApplicationTest extends TestCase
{
    use InteractsWithIdosell;
    use RefreshDatabase;

    private const ACCESS_DENIED = 'Brak dostępu. Uruchom aplikację z panelu IdoSell.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withIdosellConfig([
            'idosell.apps.application_id' => 12345,
            'idosell.apps.developer' => 'demo_dev',
            'idosell.apps.application_key' => str_repeat('1', 32),
            'idosell.apps.type' => 'online',
            'idosell.apps.base_url' => 'https://apps.idosell.com/api',
            'idosell.apps.keyset_url' => 'https://apps.idosell.com/keyset',
            'idosell.apps.sign_date_tolerance_days' => 1,
            'idosell.launch.route' => 'app.panel',
            'idosell.launch.signed' => true,
            'idosell.launch.ttl' => 30,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $shops
     */
    private function fakeShopsData(string $panelUrl, array $shops, int $status = 200): void
    {
        Http::fake([
            $panelUrl.'/api/admin/v8/system/shopsData*' => Http::response(
                $status === 200 ? ['shop_contact' => $shops] : ['errors' => ['faultString' => 'Error']],
                $status,
            ),
        ]);
    }

    private function licenseFor(int $clientId, string $panelUrl): IdosellLicense
    {
        return IdosellLicense::factory()->create([
            'client_id' => $clientId,
            'application_id' => 12345,
            'api_url' => $panelUrl,
            'api_key' => 'secret_license_key_'.$clientId,
            'authorization_type' => 'key',
            'active' => true,
        ]);
    }

    /**
     * Otwiera panel podpisanym URL (jak po launch) i zwraca podpisany adres formularza.
     */
    private function openPanel(int $clientId): string
    {
        $response = $this->get(URL::temporarySignedRoute('app.panel', now()->addMinutes(30), [
            'client' => $clientId,
            'application' => 12345,
        ]));

        $response->assertOk();
        $response->assertSee('Pokaż sklepy');

        return (string) $response->viewData('fetchUrl');
    }

    private function clickShowShops(string $fetchUrl): TestResponse
    {
        // Bez ciasteczek — jak w iframe panelu IdoSell, gdzie sesja nie dociera.
        $this->flushSession();

        return $this->post($fetchUrl);
    }

    public function test_unsigned_panel_url_returns_403(): void
    {
        $this->get('/?client=555001')->assertForbidden();
    }

    public function test_signed_panel_without_client_returns_403(): void
    {
        $this->get(URL::signedRoute('app.panel'))->assertForbidden();
    }

    public function test_full_flow_new_license_webhook_launch_panel_and_shops(): void
    {
        $this->fakeIdosellApps([
            'https://flow-shop.iai-shop.com/api/admin/v8/system/shopsData*' => Http::response([
                'shop_contact' => [
                    ['shop_id' => 1, 'shop_name' => 'flow-shop.iai-shop.com'],
                    ['shop_id' => 2, 'shop_name' => 'Drugi sklep', 'shop_url' => 'https://drugi.example.com'],
                ],
            ], 200),
        ]);

        $this->postIdosellNewLicense([
            'client_id' => 555010,
            'api_url' => 'https://flow-shop.iai-shop.com/api',
            'api_key' => $this->idosellEncryptedApiKey('flow-admin-api-key'),
        ])->assertOk()->assertJson(['status' => 'ok']);

        $launch = $this->postIdosellLaunch(['client_id' => 555010])
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $redirect = (string) $launch->json('redirect');
        $this->assertStringContainsString('client=555010', $redirect);
        $this->assertStringContainsString('signature=', $redirect);

        $panel = $this->get($redirect)->assertOk();
        $fetchUrl = (string) $panel->viewData('fetchUrl');

        $response = $this->clickShowShops($fetchUrl);

        $response->assertOk();
        $response->assertSee('flow-shop.iai-shop.com');
        $response->assertSee('https://flow-shop.iai-shop.com');
        $response->assertSee('Drugi sklep');
        $response->assertSee('https://drugi.example.com');

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://flow-shop.iai-shop.com/api/admin/v8/system/shopsData'
            && $request->hasHeader('X-API-KEY', 'flow-admin-api-key'));
    }

    public function test_signed_panel_shows_shops_from_license(): void
    {
        $this->licenseFor(555002, 'https://signed-shop.iai-shop.com');
        $this->fakeShopsData('https://signed-shop.iai-shop.com', [
            ['shop_id' => 15, 'shop_name' => 'signed-shop.iai-shop.com'],
        ]);

        $response = $this->clickShowShops($this->openPanel(555002));

        $response->assertOk();
        $response->assertSee('signed-shop.iai-shop.com');
        $response->assertSee('Pokaż sklepy');

        Http::assertSent(fn (HttpRequest $request): bool => $request->hasHeader('X-API-KEY', 'secret_license_key_555002'));
    }

    public function test_post_without_signature_shows_access_denied_without_calling_api(): void
    {
        Http::fake();

        $response = $this->post('/', ['client' => 555002]);

        $response->assertOk();
        $response->assertSee(self::ACCESS_DENIED);
        Http::assertNothingSent();
    }

    public function test_client_in_body_cannot_override_signed_client(): void
    {
        $this->licenseFor(555002, 'https://victim-shop.iai-shop.com');
        Http::fake();

        $response = $this->post('/?client=555002&signature=forged', ['client' => 555002]);

        $response->assertOk();
        $response->assertSee(self::ACCESS_DENIED);
        Http::assertNothingSent();
    }

    public function test_expired_signed_fetch_url_shows_access_denied(): void
    {
        $this->licenseFor(555004, 'https://expired-shop.iai-shop.com');
        Http::fake();

        $fetchUrl = $this->openPanel(555004);
        $this->travel(31)->minutes();

        $response = $this->clickShowShops($fetchUrl);

        $response->assertSee(self::ACCESS_DENIED);
        Http::assertNothingSent();
    }

    public function test_missing_or_inactive_license_shows_message_without_calling_api(): void
    {
        IdosellLicense::factory()->inactive()->create(['client_id' => 555005, 'application_id' => 12345]);
        Http::fake();

        $this->clickShowShops($this->openPanel(555005))->assertSee('Brak aktywnej licencji w systemie.');
        $this->clickShowShops($this->openPanel(555006))->assertSee('Brak aktywnej licencji w systemie.');

        Http::assertNothingSent();
    }

    public function test_unauthorized_api_key_error_401(): void
    {
        config(['app.debug' => false]);
        $this->licenseFor(555007, 'https://auth-shop.iai-shop.com');
        $this->fakeShopsData('https://auth-shop.iai-shop.com', [], 401);

        $response = $this->clickShowShops($this->openPanel(555007));

        $response->assertSee('Błąd autoryzacji: Odrzucono klucz API sklepu (status HTTP 401).');
    }

    public function test_other_http_error_500_is_reported_with_general_message(): void
    {
        config(['app.debug' => false]);
        $reported = [];
        $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->reportable(function (\Throwable $e) use (&$reported): bool {
                $reported[] = $e;

                return false;
            });

        $this->licenseFor(555008, 'https://error-shop.iai-shop.com');
        $this->fakeShopsData('https://error-shop.iai-shop.com', [], 500);

        $response = $this->clickShowShops($this->openPanel(555008));

        $response->assertSee('Błąd IdoSell Admin API (status HTTP 500)');
        $response->assertDontSee('faultString');
        $this->assertCount(1, $reported);
    }

    public function test_connection_exception_shows_message(): void
    {
        config(['app.debug' => false]);
        $this->licenseFor(555009, 'https://down-shop.iai-shop.com');
        Http::fake([
            'https://down-shop.iai-shop.com/*' => fn () => throw new ConnectionException('Could not resolve host'),
        ]);

        $response = $this->clickShowShops($this->openPanel(555009));

        $response->assertSee('Brak połączenia z serwerem IdoSell');
        $response->assertDontSee('Could not resolve host');
    }
}
