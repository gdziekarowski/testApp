<?php

declare(strict_types=1);

namespace App\Listeners;

use Idosell\LaravelAppSdk\Events\AppLaunched;
use Idosell\LaravelAppSdk\Events\LicenseActivated;
use Idosell\LaravelAppSdk\Events\LicenseDeactivated;
use Idosell\LaravelAppSdk\Events\LicenseDeactivating;
use Idosell\LaravelAppSdk\Models\IdosellLicense;
use Idosell\LaravelAppSdk\Support\LogChannel;
use Illuminate\Events\Dispatcher;

/**
 * Loguje przebieg cyklu życia licencji (kanał `idosell`), żeby przy problemie z instalacją
 * było widać, na którym kroku się zatrzymała.
 *
 * Logujemy wyłącznie identyfikatory i flagi. Payload webhooka zawiera `api_license`,
 * zaszyfrowany `api_key` i `contact_data` (dane osobowe), a `redirect` z launch to
 * podpisany link do panelu — żadne z nich nie trafia do logu.
 */
class LogIdosellLifecycle
{
    public function activated(LicenseActivated $event): void
    {
        LogChannel::resolve()->info('Licencja aktywowana.', [
            ...$this->license($event->license),
            'is_new' => $event->isNew,
            'installation_confirmed' => $event->license->installation_confirmed,
            'shops' => count($event->license->shops()),
        ]);
    }

    public function deactivating(LicenseDeactivating $event): void
    {
        LogChannel::resolve()->info('Licencja w trakcie deaktywacji.', $this->license($event->license));
    }

    public function deactivated(LicenseDeactivated $event): void
    {
        LogChannel::resolve()->info('Licencja zdeaktywowana.', [
            ...$this->license($event->license),
            'deleted' => $event->deleted,
        ]);
    }

    public function launched(AppLaunched $event): void
    {
        $redirect = parse_url($event->redirect);

        LogChannel::resolve()->info('Uruchomienie aplikacji z panelu IdoSell.', [
            'client_id' => $event->payload['client_id'] ?? null,
            'application_id' => $event->payload['application_id'] ?? null,
            'redirect_path' => $redirect['path'] ?? null,
            'redirect_signed' => str_contains($redirect['query'] ?? '', 'signature='),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            LicenseActivated::class => 'activated',
            LicenseDeactivating::class => 'deactivating',
            LicenseDeactivated::class => 'deactivated',
            AppLaunched::class => 'launched',
        ];
    }

    /**
     * @return array{client_id: int, application_id: int, active: bool, domain: string|null, authorization_type: string}
     */
    private function license(IdosellLicense $license): array
    {
        return [
            'client_id' => (int) $license->client_id,
            'application_id' => (int) $license->application_id,
            'active' => (bool) $license->active,
            'domain' => $license->domain(),
            'authorization_type' => (string) $license->authorization_type,
        ];
    }
}
