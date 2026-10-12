<?php

declare(strict_types=1);

namespace App\Listeners;

use Idosell\LaravelAppSdk\Support\LogChannel;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Client\Events\ResponseReceived;

/**
 * Loguje KSZTAŁT odpowiedzi Apps API (`application/license`, `installation/done`), żeby jedna
 * instalacja w panelu demo wystarczyła do sprawdzenia, czy SDK poprawnie czyta odpowiedź
 * (weryfikacja właściciela licencji: `license[].number`, `license[].client.id`).
 *
 * Logujemy wyłącznie nazwy pól, typy, flagi i identyfikatory liczbowe. Bez numeru licencji
 * (`number` = `api_license`, sekret), bez nazwy klienta i innych danych osobowych.
 */
class LogIdosellAppsApi
{
    public function received(ResponseReceived $event): void
    {
        $url = $event->request->url();

        if (str_contains($url, '/application/license') && !str_contains($url, 'setPrice')) {
            $this->logLicense($event);
        } elseif (str_contains($url, '/application/installation/done')) {
            LogChannel::resolve()->info('Apps API installation/done: odpowiedź.', [
                'http_status' => $event->response->status(),
                'status' => $event->response->json('status'),
                'keys' => $this->keys($event->response->json()),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [ResponseReceived::class => 'received'];
    }

    private function logLicense(ResponseReceived $event): void
    {
        $json = $event->response->json();
        $requested = (string) ($event->request->data()['api_license'] ?? '');
        $entries = is_array($json) ? ($json['license'] ?? $json['licenses'] ?? null) : null;

        LogChannel::resolve()->info('Apps API application/license: kształt odpowiedzi.', [
            'http_status' => $event->response->status(),
            'status' => is_array($json) ? ($json['status'] ?? null) : null,
            'top_level_keys' => $this->keys($json),
            'license_container' => is_array($json) ? (array_key_exists('license', $json) ? 'license' : (array_key_exists('licenses', $json) ? 'licenses' : null)) : null,
            'license_type' => $this->describeContainer($entries),
            'entries' => array_map(
                fn (array $entry): array => $this->describeEntry($entry, $requested),
                $this->entries($entries),
            ),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function entries(mixed $entries): array
    {
        if (!is_array($entries)) {
            return [];
        }

        // Pojedynczy obiekt zamiast listy.
        if (array_key_exists('number', $entries) || array_key_exists('client', $entries)) {
            return [$entries];
        }

        return array_slice(array_values(array_filter($entries, 'is_array')), 0, 5);
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function describeEntry(array $entry, string $requested): array
    {
        $client = $entry['client'] ?? null;
        $technical = $entry['technical'] ?? null;

        return [
            'keys' => $this->keys($entry),
            'number_present' => array_key_exists('number', $entry),
            'number_matches_request' => isset($entry['number']) && $requested !== ''
                && hash_equals((string) $entry['number'], $requested),
            'application_id' => $entry['application_id'] ?? null,
            'active' => $entry['active'] ?? null,
            'client_type' => get_debug_type($client),
            'client_keys' => $this->keys($client),
            'client_id' => is_array($client) ? ($client['id'] ?? null) : null,
            'client_id_type' => is_array($client) ? get_debug_type($client['id'] ?? null) : null,
            'technical_keys' => $this->keys($technical),
            'installation_completed' => is_array($technical) ? ($technical['installation_completed'] ?? null) : null,
        ];
    }

    private function describeContainer(mixed $entries): string
    {
        if (!is_array($entries)) {
            return get_debug_type($entries);
        }

        return array_is_list($entries) ? 'list('.count($entries).')' : 'object';
    }

    /**
     * @return array<int, string>|null
     */
    private function keys(mixed $value): ?array
    {
        return is_array($value) ? array_map('strval', array_keys($value)) : null;
    }
}
