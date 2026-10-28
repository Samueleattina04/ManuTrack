<?php

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Snapshot del componente Livewire $name preso dall'HTML di una pagina, come lo vede il browser.
 */
function livewireSnapshot(TestResponse|string $page, string $name): string
{
    $html = $page instanceof TestResponse ? (string) $page->getContent() : $page;
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
    foreach ($matches[1] as $raw) {
        $json = htmlspecialchars_decode($raw, ENT_QUOTES);
        if ((json_decode($json, true)['memo']['name'] ?? null) === $name) {
            return $json;
        }
    }
    throw new RuntimeException("Nessun componente {$name} nella pagina");
}

/** Snapshot con alcuni dati cambiati a mano (la firma non torna più) */
function tamperSnapshot(string $snapshot, array $data): string
{
    $decoded = json_decode($snapshot, true);
    $decoded['data'] = array_merge($decoded['data'], $data);

    return (string) json_encode($decoded);
}

/**
 * Richiesta costruita a mano verso /livewire/update, come farebbe chi usa la console del browser.
 *
 * @param  array<string, mixed>  $updates
 * @param  list<array{0: string, 1?: array<int, mixed>}>  $calls  [metodo, parametri]
 */
function livewireCall(TestCase $test, string $snapshot, array $calls = [], array $updates = [], array $headers = []): TestResponse
{
    // Come il JavaScript di Livewire: JSON nel corpo, senza Accept: application/json (si può forzare con $headers)
    return $test->json('POST', app('livewire')->getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => $updates,
            'calls' => array_map(fn (array $c) => ['path' => '', 'method' => $c[0], 'params' => $c[1] ?? []], $calls),
        ]],
    ], $headers + ['X-Livewire' => 'true', 'Accept' => '*/*']);
}
