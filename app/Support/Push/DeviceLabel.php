<?php

namespace App\Support\Push;

/**
 * Nome leggibile del dispositivo ricavato dallo user agent, per l'elenco dei
 * dispositivi iscritti (es. "iPhone · Safari", "Android · Chrome", "Windows · Edge").
 *
 * @return array{label: string, platform: string}
 */
final class DeviceLabel
{
    /**
     * @return array{label: string, platform: string}
     */
    public static function from(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        $platform = match (true) {
            (bool) preg_match('/iPhone|iPod/i', $ua) => 'ios',
            (bool) preg_match('/iPad/i', $ua) => 'ipados',
            (bool) preg_match('/Android/i', $ua) => 'android',
            (bool) preg_match('/Windows/i', $ua) => 'windows',
            (bool) preg_match('/Macintosh|Mac OS X/i', $ua) => 'macos',
            (bool) preg_match('/Linux|CrOS/i', $ua) => 'linux',
            default => 'other',
        };

        $device = match ($platform) {
            'ios' => 'iPhone',
            'ipados' => 'iPad',
            'android' => 'Android',
            'windows' => 'Windows',
            'macos' => 'Mac',
            'linux' => 'Linux',
            default => 'Dispositivo',
        };

        $browser = match (true) {
            (bool) preg_match('/EdgA?\//', $ua) => 'Edge',
            (bool) preg_match('/SamsungBrowser/', $ua) => 'Samsung Internet',
            (bool) preg_match('/Firefox|FxiOS/', $ua) => 'Firefox',
            (bool) preg_match('/CriOS|Chrome/', $ua) => 'Chrome',
            (bool) preg_match('/Safari/', $ua) => 'Safari',
            default => null,
        };

        return ['label' => $browser ? "{$device} · {$browser}" : $device, 'platform' => $platform];
    }
}
