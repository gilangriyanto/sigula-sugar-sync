<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

use InvalidArgumentException;

/**
 * Membuat backed enum menerima input dari beberapa bentuk sekaligus:
 * nilai kanonik ("ns1"), label tampilan ("NS 1"), maupun variasi kapital/spasi.
 *
 * Dibutuhkan karena frontend SIGULA memakai label ("NS 1", "Sedang Diproses")
 * sementara database menyimpan slug yang aman untuk indexing.
 */
trait ResolvesFromInput
{
    abstract public function label(): string;

    public static function tryFromAny(mixed $value): ?static
    {
        if ($value instanceof static) {
            return $value;
        }

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $needle = self::normalize((string) $value);

        foreach (static::cases() as $case) {
            if (self::normalize((string) $case->value) === $needle) {
                return $case;
            }

            if (self::normalize($case->label()) === $needle) {
                return $case;
            }
        }

        return null;
    }

    public static function fromAny(mixed $value): static
    {
        return static::tryFromAny($value) ?? throw new InvalidArgumentException(
            sprintf('Nilai "%s" tidak valid untuk %s.', is_scalar($value) ? (string) $value : gettype($value), static::class)
        );
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => (string) $case->value, static::cases());
    }

    /** @return array<int, string> */
    public static function labels(): array
    {
        return array_map(static fn (self $case): string => $case->label(), static::cases());
    }

    /**
     * Daftar input yang diterima validator (nilai kanonik + label tampilan).
     *
     * @return array<int, string>
     */
    public static function acceptedInputs(): array
    {
        return array_values(array_unique([...static::values(), ...static::labels()]));
    }

    /** @return array<string, string> daftar opsi untuk dropdown frontend */
    public static function options(): array
    {
        $out = [];

        foreach (static::cases() as $case) {
            $out[(string) $case->value] = $case->label();
        }

        return $out;
    }

    private static function normalize(string $value): string
    {
        return str_replace([' ', '-', '_'], '', mb_strtolower(trim($value)));
    }
}
