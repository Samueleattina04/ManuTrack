<?php

namespace App\Support\Update;

/**
 * Esito di un tentativo di aggiornamento.
 */
final class UpdateResult
{
    public const SUCCESS = 'success';

    public const UP_TO_DATE = 'up_to_date';

    public const FAILED = 'failed';

    public const LOCKED = 'locked';

    public const SKIPPED = 'skipped';

    public const NOT_INSTALLED = 'not_installed';

    private function __construct(
        public readonly string $status,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?string $message = null,
        public readonly ?string $backup = null,
    ) {}

    public static function success(?string $from, string $to, ?string $backup): self
    {
        return new self(self::SUCCESS, $from, $to, null, $backup);
    }

    public static function upToDate(string $version): self
    {
        return new self(self::UP_TO_DATE, $version, $version);
    }

    public static function failed(?string $from, string $to, string $message, ?string $backup): self
    {
        return new self(self::FAILED, $from, $to, $message, $backup);
    }

    public static function locked(): self
    {
        return new self(self::LOCKED, message: __('system.update.locked'));
    }

    public static function skipped(string $reason): self
    {
        return new self(self::SKIPPED, message: $reason);
    }

    public static function notInstalled(): self
    {
        return new self(self::NOT_INSTALLED, message: __('system.update.not_installed'));
    }

    public function ok(): bool
    {
        return in_array($this->status, [self::SUCCESS, self::UP_TO_DATE, self::SKIPPED], true);
    }
}
