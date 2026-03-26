<?php

declare(strict_types=1);

namespace Kasumi;

/**
 * Encodes a scrambled integer as a zero-padded base36 string (0-9a-z).
 * Always returns exactly 13 characters — the maximum needed for PHP_INT_MAX.
 */
final class Base36Encoder implements Encoder
{
    /** Number of base36 digits required to represent PHP_INT_MAX (= 2^63 - 1). */
    private const LENGTH = 13;

    public function encode(int $n): string
    {
        return str_pad(base_convert((string) $n, 10, 36), self::LENGTH, '0', STR_PAD_LEFT);
    }

    public function decode(string $s): int
    {
        return (int) base_convert(ltrim($s, '0') ?: '0', 36, 10);
    }
}
