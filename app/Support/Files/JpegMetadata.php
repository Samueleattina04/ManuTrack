<?php

namespace App\Support\Files;

/**
 * Metadati dei JPEG letti e tolti in PHP puro, senza l'estensione "exif"
 * (spesso assente sugli hosting condivisi):
 * - orientation(): come è stato tenuto il telefono durante lo scatto (EXIF Orientation);
 * - strip(): toglie EXIF (posizione GPS compresa), XMP e commenti senza ricomprimere.
 */
final class JpegMetadata
{
    /**
     * Orientamento EXIF da 1 a 8 (1 = dritta). 1 se manca o il file non è leggibile.
     */
    public static function orientation(string $jpeg): int
    {
        foreach (self::segments($jpeg) as [$marker, $data]) {
            if ($marker !== 0xE1 || strncmp($data, "Exif\0\0", 6) !== 0) {
                continue;
            }
            $tiff = substr($data, 6);
            $little = strncmp($tiff, 'II', 2) === 0;
            if (! $little && strncmp($tiff, 'MM', 2) !== 0) {
                return 1;
            }
            $u16 = fn (int $at) => (int) (unpack($little ? 'v' : 'n', substr($tiff, $at, 2))[1] ?? 0);
            $u32 = fn (int $at) => (int) (unpack($little ? 'V' : 'N', substr($tiff, $at, 4))[1] ?? 0);

            $ifd = $u32(4);
            if ($ifd <= 0 || $ifd + 2 > strlen($tiff)) {
                return 1;
            }
            $count = $u16($ifd);
            for ($i = 0; $i < $count; $i++) {
                $entry = $ifd + 2 + $i * 12;
                if ($entry + 12 > strlen($tiff)) {
                    break;
                }
                if ($u16($entry) === 0x0112) {
                    $value = $u16($entry + 8);

                    return $value >= 1 && $value <= 8 ? $value : 1;
                }
            }

            return 1;
        }

        return 1;
    }

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

    /**
     * Blocchi di intestazione del JPEG fino ai dati compressi.
     *
     * @return list<array{0: int, 1: string}>
     */
    private static function segments(string $jpeg): array
    {
        if (strncmp($jpeg, "\xFF\xD8", 2) !== 0) {
            return [];
        }
        $out = [];
        $pos = 2;
        $len = strlen($jpeg);
        while ($pos + 4 <= $len && $jpeg[$pos] === "\xFF") {
            $marker = ord($jpeg[$pos + 1]);
            if ($marker === 0xDA) {
                break;
            }
            $size = (int) unpack('n', substr($jpeg, $pos + 2, 2))[1];
            $out[] = [$marker, substr($jpeg, $pos + 4, max(0, $size - 2))];
            $pos += $size + 2;
        }

        return $out;
    }
}
