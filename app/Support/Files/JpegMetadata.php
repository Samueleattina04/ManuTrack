<?php

namespace App\Support\Files;

/**
 * Toglie da un JPEG i blocchi di metadati (EXIF con la posizione GPS, XMP,
 * commenti) senza ricomprimere l'immagine. Serve quando sul server mancano
 * GD e Imagick: in quel caso le foto si salvano così come sono, ma senza GPS.
 */
final class JpegMetadata
{
    public static function strip(string $jpeg): string
    {
        if (strncmp($jpeg, "\xFF\xD8", 2) !== 0) {
            return $jpeg;
        }

        $out = "\xFF\xD8";
        $pos = 2;
        $len = strlen($jpeg);

        while ($pos + 4 <= $len) {
            if ($jpeg[$pos] !== "\xFF") {
                return $jpeg; // struttura inattesa: meglio non toccare
            }
            $marker = ord($jpeg[$pos + 1]);

            // Inizio dei dati compressi: il resto si copia così com'è
            if ($marker === 0xDA) {
                return $out.substr($jpeg, $pos);
            }

            $size = unpack('n', substr($jpeg, $pos + 2, 2))[1];
            $segment = substr($jpeg, $pos, $size + 2);

            // APP1 (EXIF, XMP), APP2–APP15 tranne APP14 (Adobe, serve ai colori), commenti
            $drop = ($marker >= 0xE1 && $marker <= 0xEF && $marker !== 0xEE) || $marker === 0xFE;
            if (! $drop) {
                $out .= $segment;
            }
            $pos += $size + 2;
        }

        return $jpeg;
    }
}
