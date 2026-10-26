<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Impostazioni dell'azienda salvate nel database, con valori iniziali da config/.env.
 *
 * Funziona anche prima dell'installazione: se la tabella non esiste restituisce
 * semplicemente i valori predefiniti.
 */
class Settings
{
    private const CACHE_KEY = 'manutrack.settings';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /**
     * Valori predefiniti, letti da config/manutrack.php e quindi da .env.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'company.name' => config('manutrack.company.name'),
            'company.palette' => config('manutrack.company.palette', 'rame'),
            'company.accent' => config('manutrack.company.accent'),
            'company.logo' => null,
            'company.locale' => config('app.locale', 'it'),
            'company.timezone' => config('app.timezone', 'Europe/Rome'),
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        if (array_key_exists($key, $values) && $values[$key] !== null) {
            return $values[$key];
        }

        return $this->defaults()[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        try {
            $this->values = Cache::rememberForever(self::CACHE_KEY, function (): array {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::query()->pluck('value', 'key')->all();
            });
        } catch (Throwable) {
            // Database non ancora configurato: si usano i valori predefiniti
            $this->values = [];
        }

        return $this->values;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $this->flush();
    }

    /**
     * Imposta i valori solo se non sono già presenti (usato da installazione e aggiornamenti).
     *
     * @param  array<string, mixed>  $values
     */
    public function ensure(array $values): void
    {
        $existing = Setting::query()->whereIn('key', array_keys($values))->pluck('key')->all();

        foreach (Arr::except($values, $existing) as $key => $value) {
            Setting::query()->create(['key' => $key, 'value' => $value]);
        }
        $this->flush();
    }

    public function forget(string $key): void
    {
        Setting::query()->whereKey($key)->delete();
        $this->flush();
    }

    public function flush(): void
    {
        $this->values = null;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // cache non disponibile: nulla da svuotare
        }
    }
}
