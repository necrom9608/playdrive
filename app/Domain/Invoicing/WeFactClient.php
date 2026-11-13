<?php

namespace App\Domain\Invoicing;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimale WeFact API v2-client (https://developer.wefact.com).
 *
 *   POST https://api.mijnwefact.nl/v2/  met api_key, controller, action + parameters (form).
 *   Antwoord: {status: success|error, errors?: [...], <controller>: {...}}
 *
 * Let op: het IP-adres van de PlayDrive-server moet in WeFact → Instellingen → API
 * op de whitelist staan.
 */
class WeFactClient
{
    private const URL = 'https://api.mijnwefact.nl/v2/';

    public function __construct(private readonly string $apiKey)
    {
    }

    /** @throws RuntimeException met de foutmelding van WeFact */
    public function call(string $controller, string $action, array $params = []): array
    {
        $response = Http::timeout(20)
            ->connectTimeout(5)
            ->acceptJson()
            ->asForm()
            ->post(self::URL, [
                'api_key'    => $this->apiKey,
                'controller' => $controller,
                'action'     => $action,
                ...$params,
            ]);

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException("WeFact gaf geen geldig antwoord (HTTP {$response->status()}).");
        }

        if (($data['status'] ?? null) !== 'success') {
            $errors = array_values(array_filter(array_map('strval', (array) ($data['errors'] ?? []))));
            throw new RuntimeException('WeFact: '.($errors ? implode(' ', $errors) : 'onbekende fout'));
        }

        return $data;
    }
}
