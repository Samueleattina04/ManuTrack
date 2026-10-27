# Librerie e licenze

Elenco delle librerie usate da ManuTrack e delle loro licenze, con la verifica di compatibilità per un **uso commerciale** (software installato presso clienti e venduto come servizio).

Aggiornato alla versione **0.8.0**. Fase 4: aggiunta `openspout/openspout` (MIT) per leggere i file Excel. Fase 5: aggiunta `jsqr` (Apache-2.0) per leggere i codici QR sui telefoni senza lettore nativo (iPhone). Fasi 6, 7 e 8: nessuna libreria nuova. Il service worker è generato da Laravel, quindi `vite-plugin-pwa` e Workbox non servono. Le fasi successive aggiungono le librerie previste in `docs/PROPOSTA.md`, ciascuna verificata prima dell'uso.

## In sintesi

| Licenza | Dove | Uso commerciale | Obblighi |
|---|---|---|---|
| MIT, BSD-2-Clause, BSD-3-Clause, ISC, 0BSD | quasi tutte le librerie PHP e JavaScript | ✅ sì | conservare l'avviso di copyright e il testo di licenza (già presenti nei pacchetti in `vendor/`) |
| Apache-2.0 | `phpoption/phpoption`, `jsqr` (nel pacchetto compilato); alcuni strumenti di sviluppo | ✅ sì | conservare l'avviso di licenza e il file NOTICE, se presente (`jsqr` non ne ha). Nel pacchetto compilato il codice di jsQR è in `public/build/assets/jsQR-*.js`; l'elenco completo degli avvisi delle librerie è previsto nella fase 11 |
| BSD-3-Clause **oppure** GPL-2.0/3.0 | `nette/schema`, `nette/utils` (dipendenze di Laravel) | ✅ sì, scegliendo la BSD-3-Clause | doppia licenza a scelta: ManuTrack usa la BSD-3-Clause |
| SIL OFL 1.1 | caratteri Archivo, IBM Plex Sans, IBM Plex Mono, Open Sans | ✅ sì | i font non si vendono da soli; ospitarli nell'app è consentito |
| MPL-2.0 | `lightningcss` (usato da Tailwind **solo durante la compilazione**) | ✅ sì | non è incluso nel pacchetto distribuito |

**Nessuna libreria GPL, AGPL o con clausole non commerciali** fa parte del pacchetto distribuito.

Le icone sono di **Lucide** (licenza ISC): il contenuto SVG è copiato in `resources/icons/lucide.php`, con l'avviso nell'intestazione del file.

## Componenti principali

| Componente | Versione | Licenza | Uso |
|---|---|---|---|
| Laravel Framework | 12.x | MIT | struttura dell'applicazione |
| Livewire | 3.x | MIT | interfaccia reattiva (Alpine.js è incluso in Livewire, MIT) |
| minishlink/web-push | 9.x | MIT | notifiche push e chiavi VAPID |
| laravel/fortify | 1.x | MIT | accesso, reset della password, verifica in due passaggi |
| spatie/laravel-permission | 6.x | MIT | ruoli e permessi |
| intervention/image | 3.x | MIT | ridimensionamento di logo e foto |
| bacon/bacon-qr-code | 3.x | BSD-2-Clause | codici QR: etichette delle macchine e app di autenticazione |
| openspout/openspout | 4.x | MIT | lettura dei file Excel (XLSX) per l'importazione delle macchine; scriverà le esportazioni |
| pragmarx/google2fa | 8.x | MIT | codici della verifica in due passaggi (dipendenza di Fortify) |
| Tailwind CSS | 4.x | MIT | stile (solo compilazione) |
| Vite, laravel-vite-plugin | 7.x / 2.x | MIT | compilazione del frontend |
| @fontsource (Archivo, IBM Plex Sans/Mono) | 5.x | OFL-1.1 (font), MIT (pacchetto) | caratteri ospitati dall'app |
| Open Sans (solo per generare la scritta del logo) | 5.x | OFL-1.1 | la scritta è convertita in tracciati in `resources/brand/wordmark.json` |
| opentype.js | 1.x | MIT | lettura del font per la scritta del logo (solo sviluppo) |
| Lucide | — | ISC | icone |
| jsQR | 1.4 | Apache-2.0 | lettura dei codici QR dalla fotocamera dove manca `BarcodeDetector` (iPhone); caricato solo quando serve |

### Strumenti di sviluppo (non inclusi nel pacchetto)

| Strumento | Licenza |
|---|---|
| Pest, PHPUnit | MIT, BSD-3-Clause |
| Larastan / PHPStan | MIT |
| Laravel Pint | MIT |
| Playwright (screenshot di controllo) | Apache-2.0 |

## Elenco completo delle dipendenze PHP distribuite

Generato con `composer licenses --no-dev`.

| Pacchetto | Versione | Licenza |
|---|---|---|
| `bacon/bacon-qr-code` | v3.1.1 | BSD-2-Clause |
| `brick/math` | 0.14.8 | MIT |
| `carbonphp/carbon-doctrine-types` | 3.2.1 | MIT |
| `dasprid/enum` | 1.0.7 | BSD-2-Clause |
| `dflydev/dot-access-data` | v3.0.3 | MIT |
| `doctrine/deprecations` | 1.1.6 | MIT |
| `doctrine/inflector` | 2.1.0 | MIT |
| `doctrine/lexer` | 3.0.3 | MIT |
| `dragonmantank/cron-expression` | v3.6.1 | MIT |
| `egulias/email-validator` | 4.0.4 | MIT |
| `fruitcake/php-cors` | v1.4.0 | MIT |
| `graham-campbell/result-type` | v1.2.0 | MIT |
| `guzzlehttp/guzzle` | 7.16.1 | MIT |
| `guzzlehttp/promises` | 2.6.0 | MIT |
| `guzzlehttp/psr7` | 2.13.2 | MIT |
| `guzzlehttp/uri-template` | v1.0.11 | MIT |
| `intervention/gif` | 4.2.4 | MIT |
| `intervention/image` | 3.11.10 | MIT |
| `laravel/fortify` | v1.41.0 | MIT |
| `laravel/framework` | v12.70.1 | MIT |
| `laravel/passkeys` | v0.2.1 | MIT |
| `laravel/prompts` | v0.3.26 | MIT |
| `laravel/serializable-closure` | v2.1.0 | MIT |
| `laravel/tinker` | v2.11.1 | MIT |
| `league/commonmark` | 2.10.4 | BSD-3-Clause |
| `league/config` | v1.2.0 | BSD-3-Clause |
| `league/flysystem` | 3.36.0 | MIT |
| `league/flysystem-local` | 3.35.3 | MIT |
| `league/mime-type-detection` | 1.17.0 | MIT |
| `league/uri` | 7.9.0 | MIT |
| `league/uri-interfaces` | 7.9.0 | MIT |
| `livewire/livewire` | v3.8.11 | MIT |
| `minishlink/web-push` | v9.0.4 | MIT |
| `monolog/monolog` | 3.12.1 | MIT |
| `nesbot/carbon` | 3.15.1 | MIT |
| `nette/schema` | v1.4.0 | BSD-3-Clause, GPL-2.0-only, GPL-3.0-only |
| `nette/utils` | v4.1.6 | BSD-3-Clause, GPL-2.0-only, GPL-3.0-only |
| `nikic/php-parser` | v5.9.0 | BSD-3-Clause |
| `nunomaduro/termwind` | v2.4.0 | MIT |
| `openspout/openspout` | v4.32.0 | MIT |
| `paragonie/constant_time_encoding` | v3.1.3 | MIT |
| `phpdocumentor/reflection-common` | 2.2.1 | MIT |
| `phpdocumentor/reflection-docblock` | 6.0.3 | MIT |
| `phpdocumentor/type-resolver` | 2.1.0 | MIT |
| `phpoption/phpoption` | 1.10.0 | Apache-2.0 |
| `phpstan/phpdoc-parser` | 2.3.6 | MIT |
| `pragmarx/google2fa` | v9.1.0 | MIT |
| `psr/clock` | 1.0.0 | MIT |
| `psr/container` | 2.0.2 | MIT |
| `psr/event-dispatcher` | 1.0.0 | MIT |
| `psr/http-client` | 1.0.3 | MIT |
| `psr/http-factory` | 1.1.0 | MIT |
| `psr/http-message` | 2.0 | MIT |
| `psr/log` | 3.0.2 | MIT |
| `psr/simple-cache` | 3.0.0 | MIT |
| `psy/psysh` | v0.12.24 | MIT |
| `ralouphie/getallheaders` | 3.0.3 | MIT |
| `ramsey/collection` | 2.1.1 | MIT |
| `ramsey/uuid` | 4.9.4 | MIT |
| `spatie/laravel-permission` | 6.25.0 | MIT |
| `spomky-labs/base64url` | v2.0.4 | MIT |
| `spomky-labs/cbor-php` | 3.4.2 | MIT |
| `spomky-labs/pki-framework` | 1.6.3 | MIT |
| `symfony/clock` | v7.4.21 | MIT |
| `symfony/console` | v7.4.21 | MIT |
| `symfony/css-selector` | v7.4.18 | MIT |
| `symfony/deprecation-contracts` | v3.7.1 | MIT |
| `symfony/error-handler` | v7.4.20 | MIT |
| `symfony/event-dispatcher` | v7.4.17 | MIT |
| `symfony/event-dispatcher-contracts` | v3.7.1 | MIT |
| `symfony/finder` | v7.4.20 | MIT |
| `symfony/http-foundation` | v7.4.21 | MIT |
| `symfony/http-kernel` | v7.4.21 | MIT |
| `symfony/mailer` | v7.4.19 | MIT |
| `symfony/mime` | v7.4.19 | MIT |
| `symfony/polyfill-ctype` | v1.37.0 | MIT |
| `symfony/polyfill-intl-grapheme` | v1.43.0 | MIT |
| `symfony/polyfill-intl-idn` | v1.43.0 | MIT |
| `symfony/polyfill-intl-normalizer` | v1.43.0 | MIT |
| `symfony/polyfill-mbstring` | v1.43.0 | MIT |
| `symfony/polyfill-php80` | v1.43.0 | MIT |
| `symfony/polyfill-php81` | v1.38.1 | MIT |
| `symfony/polyfill-php82` | v1.43.0 | MIT |
| `symfony/polyfill-php83` | v1.43.0 | MIT |
| `symfony/polyfill-php84` | v1.43.0 | MIT |
| `symfony/polyfill-php85` | v1.43.0 | MIT |
| `symfony/polyfill-time` | v1.42.0 | MIT |
| `symfony/polyfill-uuid` | v1.43.0 | MIT |
| `symfony/process` | v7.4.21 | MIT |
| `symfony/property-access` | v7.4.16 | MIT |
| `symfony/property-info` | v7.4.20 | MIT |
| `symfony/routing` | v7.4.21 | MIT |
| `symfony/serializer` | v7.4.21 | MIT |
| `symfony/service-contracts` | v3.7.3 | MIT |
| `symfony/string` | v7.4.21 | MIT |
| `symfony/translation` | v7.4.17 | MIT |
| `symfony/translation-contracts` | v3.7.1 | MIT |
| `symfony/type-info` | v7.4.20 | MIT |
| `symfony/uid` | v7.4.21 | MIT |
| `symfony/var-dumper` | v7.4.21 | MIT |
| `tijsverkoyen/css-to-inline-styles` | v2.4.0 | BSD-3-Clause |
| `vlucas/phpdotenv` | v5.8.0 | BSD-3-Clause |
| `voku/portable-ascii` | 2.1.1 | MIT |
| `web-auth/cose-lib` | 4.8.2 | MIT |
| `web-auth/webauthn-lib` | 5.3.9 | MIT |
| `web-token/jwt-library` | 4.2.3 | MIT |
| `webmozart/assert` | 2.5.0 | MIT |

## Come rigenerare questo elenco

```bash
composer licenses --no-dev
npm ls --omit=dev --all
```
