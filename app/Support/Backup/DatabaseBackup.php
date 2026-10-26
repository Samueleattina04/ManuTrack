<?php

namespace App\Support\Backup;

use App\Support\AppVersion;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Copia e ripristino del database MySQL/MariaDB scritti in PHP puro (PDO), senza
 * exec() né mysqldump, che sugli hosting condivisi spesso non sono disponibili.
 *
 * Il file è SQL compresso con gzip, un'istruzione per riga: i valori sono resi
 * con le sequenze di escape di MySQL e i dati binari in esadecimale.
 */
class DatabaseBackup
{
    /** Tabelle di cui si salva solo la struttura */
    private const STRUCTURE_ONLY = ['cache', 'cache_locks', 'sessions'];

    private const MAX_STATEMENT_BYTES = 1_000_000;

    public function __construct(private ?Connection $connection = null)
    {
        $this->connection ??= DB::connection();
    }

    public function directory(): string
    {
        $dir = (string) config('manutrack.backups.path', storage_path('app/backups'));
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $guard = $dir.DIRECTORY_SEPARATOR.'.htaccess';
        if (! is_file($guard)) {
            file_put_contents($guard, "Require all denied\nDeny from all\n");
        }

        return $dir;
    }

    /**
     * Crea una copia e restituisce il percorso del file.
     */
    public function create(string $label = 'manuale'): string
    {
        $this->assertMysql();

        $label = preg_replace('/[^a-z0-9.\-]+/i', '-', $label) ?: 'copia';
        $file = $this->directory().DIRECTORY_SEPARATOR.'manutrack-'.now()->format('Ymd-His').'-'.$label.'.sql.gz';
        $tmp = $file.'.part';

        $gz = gzopen($tmp, 'wb6');
        if ($gz === false) {
            throw new RuntimeException("Impossibile scrivere {$tmp}");
        }

        $pdo = $this->pdo();
        $database = $this->connection->getDatabaseName();

        $this->write($gz, '-- ManuTrack: copia del database');
        $this->write($gz, '-- versione: '.AppVersion::code().' | database: '.$database.' | creata: '.now()->toIso8601String());
        $this->write($gz, 'SET NAMES utf8mb4;');
        $this->write($gz, 'SET FOREIGN_KEY_CHECKS=0;');
        $this->write($gz, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';");

        foreach ($this->tables() as $table) {
            $create = $pdo->query('SHOW CREATE TABLE '.$this->ident($table))->fetch(PDO::FETCH_NUM);
            $this->write($gz, 'DROP TABLE IF EXISTS '.$this->ident($table).';');
            $this->write($gz, str_replace(["\r\n", "\n", "\r"], ' ', (string) $create[1]).';');

            if (! in_array($table, self::STRUCTURE_ONLY, true)) {
                $this->dumpRows($gz, $pdo, $table);
            }
        }

        $this->write($gz, 'SET FOREIGN_KEY_CHECKS=1;');
        $this->write($gz, '-- fine');
        gzclose($gz);

        rename($tmp, $file);

        return $file;
    }

    /**
     * Ripristina un file creato da create(). Tutte le tabelle attuali vengono eliminate.
     */
    public function restore(string $file): void
    {
        $this->assertMysql();

        if (! is_file($file)) {
            throw new RuntimeException("File non trovato: {$file}");
        }

        // Il file deve essere completo: l'ultima riga è "-- fine"
        if (! $this->isComplete($file)) {
            throw new RuntimeException('La copia è incompleta o danneggiata: ripristino annullato.');
        }

        $gz = gzopen($file, 'rb');
        if ($gz === false) {
            throw new RuntimeException("Impossibile leggere {$file}");
        }

        $pdo = $this->pdo();
        $pdo->exec("SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'NO_BACKSLASH_ESCAPES', '')");
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        foreach ($this->tables() as $table) {
            $pdo->exec('DROP TABLE IF EXISTS '.$this->ident($table));
        }

        while (($line = gzgets($gz)) !== false) {
            $sql = rtrim($line, "\r\n");
            if ($sql === '' || str_starts_with($sql, '--')) {
                continue;
            }
            $pdo->exec($sql);
        }

        gzclose($gz);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    public function isComplete(string $file): bool
    {
        $gz = @gzopen($file, 'rb');
        if ($gz === false) {
            return false;
        }

        $last = '';
        while (($line = gzgets($gz)) !== false) {
            if (trim($line) !== '') {
                $last = trim($line);
            }
        }
        gzclose($gz);

        return $last === '-- fine';
    }

    /**
     * Copie presenti, dalla più recente.
     *
     * @return list<array{file: string, name: string, size: int, created: int}>
     */
    public function list(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.'manutrack-*.sql.gz') ?: [];

        $items = array_map(fn (string $f) => [
            'file' => $f,
            'name' => basename($f),
            'size' => (int) filesize($f),
            'created' => (int) filemtime($f),
        ], $files);

        // Il nome contiene data e ora: l'ordine alfabetico inverso è cronologico
        usort($items, fn ($a, $b) => strcmp($b['name'], $a['name']));

        return $items;
    }

    /**
     * Conserva solo le ultime $keep copie. Restituisce quante ne ha eliminate.
     */
    public function prune(?int $keep = null): int
    {
        $keep = max(1, $keep ?? (int) config('manutrack.backups.keep', 5));
        $removed = 0;

        foreach (array_slice($this->list(), $keep) as $old) {
            if (@unlink($old['file'])) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        $rows = $this->pdo()
            ->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")
            ->fetchAll(PDO::FETCH_NUM);

        return array_map(fn ($r) => (string) $r[0], $rows);
    }

    /**
     * @param  resource  $gz
     */
    private function dumpRows($gz, PDO $pdo, string $table): void
    {
        $buffered = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);

        try {
            $stmt = $pdo->query('SELECT * FROM '.$this->ident($table));
            $columns = null;
            $values = [];
            $bytes = 0;

            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                if ($columns === null) {
                    $columns = '('.implode(',', array_map(fn ($c) => $this->ident((string) $c), array_keys($row))).')';
                }

                $tuple = '('.implode(',', array_map(fn ($v) => $this->literal($v), $row)).')';
                $values[] = $tuple;
                $bytes += strlen($tuple);

                if ($bytes >= self::MAX_STATEMENT_BYTES || count($values) >= (int) config('manutrack.backups.chunk', 500)) {
                    $this->write($gz, 'INSERT INTO '.$this->ident($table).' '.$columns.' VALUES '.implode(',', $values).';');
                    $values = [];
                    $bytes = 0;
                }
            }

            if ($values !== []) {
                $this->write($gz, 'INSERT INTO '.$this->ident($table).' '.$columns.' VALUES '.implode(',', $values).';');
            }

            $stmt->closeCursor();
        } finally {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
        }
    }

    private function literal(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $value = (string) $value;

        if (! mb_check_encoding($value, 'UTF-8')) {
            return $value === '' ? "''" : '0x'.bin2hex($value);
        }

        return "'".strtr($value, [
            '\\' => '\\\\',
            "\0" => '\\0',
            "\n" => '\\n',
            "\r" => '\\r',
            "'" => "\\'",
            "\x1a" => '\\Z',
        ])."'";
    }

    private function ident(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    /**
     * @param  resource  $gz
     */
    private function write($gz, string $line): void
    {
        gzwrite($gz, $line."\n");
    }

    /**
     * PDO della connessione, riaperta se era stata chiusa.
     */
    private function pdo(): PDO
    {
        $this->connection->select('select 1');

        return $this->connection->getPdo();
    }

    private function assertMysql(): void
    {
        if (! in_array($this->connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Le copie del database sono disponibili solo con MySQL o MariaDB.');
        }
    }
}
