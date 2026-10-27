<?php

namespace App\Support\Files;

use RuntimeException;

/**
 * Foto che il server non riesce a trattare. Con $keepOriginal la foto è valida
 * ma in un formato che il server non sa convertire (HEIC): conviene conservarla
 * così com'è anziché perderla.
 */
class PhotoException extends RuntimeException
{
    public bool $keepOriginal = false;

    public static function heic(): self
    {
        $e = new self(__('assets.files.photo_heic'));
        $e->keepOriginal = true;

        return $e;
    }
}
