<?php

declare(strict_types=1);

namespace Tests\Feature;

use Idosell\LaravelAppSdk\Facades\Idosell;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Idosell\LaravelAppSdk\Testing\InteractsWithIdosell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Tests\TestCase;

/**
 * Kanał `idosell` ma pozwolić ustalić, na którym kroku integracja się zatrzymała,
 * i nie może zawierać sekretów ani podpisanych linków.
 */
class IdosellLoggingTest extends TestCase
{
    use InteractsWithIdosell;
    use RefreshDatabase;

    private TestHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withIdosellConfig(['idosell.apps.application_id' => 12345]);

        $this->handler = new TestHandler();
        config(['logging.channels.idosell' => ['driver' => 'monolog', 'handler' => TestHandler::class]]);
        Log::forgetChannel('idosell');
        Log::channel('idosell')->getLogger()->setHandlers([$this->handler]);
    }

    /**
     * @return array<int, string>
     */
    private function messages(): array
    {
        return array_map(fn (LogRecord $record): string => $record->message, $this->handler->getRecords());
    }

    private function allLogged(): string
    {
        return (string) json_encode(array_map(
            fn (LogRecord $record): array => [$record->message, $record->context],
            $this->handler->getRecords(),
        ));
    }

    public function test_logs_rejected_panel_entry_with_reason_fields(): void
    {
        $this->get('/panel?client=555001&application=12345')->assertForbidden();

        $this->assertContains('Panel ▶ GET /panel', $this->messages());
        $this->assertContains('Panel ◀ /panel → HTTP 403', $this->messages());

        $entry = collect($this->handler->getRecords())->first(fn (LogRecord $r): bool => $r->message === 'Panel ▶ GET /panel');
        $this->assertSame('555001', $entry->context['client']);
        $this->assertFalse($entry->context['has_signature']);
        $this->assertFalse($entry->context['signature_valid']);
    }

    public function test_logs_accepted_panel_entry_without_signature_value(): void
    {
        $license = IdosellLicense::factory()->create(['client_id' => 555002, 'application_id' => 12345]);
        $url = Idosell::panelUrl('app.panel', [], $license);

        $this->get($url)->assertOk();

        $this->assertContains('Panel ◀ /panel → HTTP 200', $this->messages());
        $this->assertTrue($this->handler->hasDebugThatContains('Panel ◀ /panel → HTTP 200'));

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertStringNotContainsString($query['signature'], $this->allLogged());
    }

    public function test_logs_license_lifecycle_and_launch_without_secrets(): void
    {
        $this->fakeIdosellApps();

        $this->postIdosellNewLicense([
            'client_id' => 555003,
            'api_key' => $this->idosellEncryptedApiKey('lifecycle-admin-api-key'),
            'contact_data' => ['email' => 'jan.kowalski@example.com'],
        ])->assertJson(['status' => 'ok']);

        $redirect = (string) $this->postIdosellLaunch(['client_id' => 555003])->json('redirect');
        $this->postIdosellRemoveLicense(['client_id' => 555003])->assertJson(['status' => 'ok']);

        $this->assertContains('Licencja aktywowana.', $this->messages());
        $this->assertContains('Uruchomienie aplikacji z panelu IdoSell.', $this->messages());
        $this->assertContains('Licencja w trakcie deaktywacji.', $this->messages());
        $this->assertContains('Licencja zdeaktywowana.', $this->messages());

        $logged = $this->allLogged();
        $this->assertStringNotContainsString('lifecycle-admin-api-key', $logged);
        $this->assertStringNotContainsString('LIC-TEST-0000000000000000', $logged);
        $this->assertStringNotContainsString('jan.kowalski@example.com', $logged);
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);
        $this->assertStringNotContainsString($query['signature'], $logged);
    }
}
