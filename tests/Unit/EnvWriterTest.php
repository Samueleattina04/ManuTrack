<?php

use App\Support\Setup\EnvWriter;

it('sostituisce, aggiunge e mette tra virgolette i valori', function () {
    $file = $this->sandbox.'/prova.env';
    file_put_contents($file, "APP_NAME=ManuTrack\nVAPID_PUBLIC_KEY=vecchia\n# commento\n");

    (new EnvWriter($file))->set([
        'VAPID_PUBLIC_KEY' => 'nuova',
        'MAIL_FROM_NAME' => 'Officine Rossi & Figli',
        'VUOTO' => '',
    ]);

    expect(file_get_contents($file))->toBe("APP_NAME=ManuTrack\nVAPID_PUBLIC_KEY=nuova\n# commento\nMAIL_FROM_NAME=\"Officine Rossi & Figli\"\nVUOTO=\n");
});
