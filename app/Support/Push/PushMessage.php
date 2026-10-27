<?php

namespace App\Support\Push;

/**
 * Contenuto di una notifica push: testo breve, pagina da aprire al tocco,
 * etichetta per raggruppare (una nuova notifica con lo stesso tag sostituisce la precedente)
 * e contatore da mostrare sull'icona dell'app.
 */
final class PushMessage
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $url = '/',
        public readonly ?string $tag = null,
        public readonly ?int $count = null,
    ) {}

    public function toJson(): string
    {
        return (string) json_encode(array_filter([
            'title' => mb_strimwidth($this->title, 0, 80, '…'),
            'body' => mb_strimwidth($this->body, 0, 180, '…'),
            'url' => $this->url,
            'tag' => $this->tag,
            'count' => $this->count,
            'timestamp' => (int) (microtime(true) * 1000),
        ], fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
