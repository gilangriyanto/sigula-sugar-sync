<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Pelanggaran aturan bisnis (stok tidak cukup, sesi sudah selesai, dsb).
 * Dirender sebagai 422 dengan format yang sama seperti error validasi Laravel
 * supaya frontend cukup punya satu jalur penanganan error.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>|string>  $errors
     */
    public function __construct(
        string $message,
        private readonly array $errors = [],
        private readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    /** @param array<string, array<int, string>|string> $errors */
    public static function untukField(string $field, string $message): self
    {
        return new self($message, [$field => [$message]]);
    }

    /** @return array<string, array<int, string>> */
    public function errors(): array
    {
        $normalized = [];

        foreach ($this->errors as $field => $messages) {
            $normalized[$field] = is_array($messages) ? array_values($messages) : [$messages];
        }

        return $normalized;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => $this->errors(),
        ], $this->status);
    }
}
