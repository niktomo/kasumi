<?php

declare(strict_types=1);

namespace Kasumi;

/**
 * Encodes a 64-bit value (upper 32 bits + lower 32 bits) as a zero-padded base36 string (0-9a-z).
 *
 * Each 32-bit half is encoded independently and concatenated:
 *   upper (0–2^32-1) → 7 chars  (36^7 = 78,364,164,096 > 2^32-1 = 4,294,967,295)
 *   lower (0–2^32-1) → 7 chars
 *   total            → 14 chars
 *
 * Both halves fit in PHP_INT_MAX, so base_convert() (C implementation) can be used directly.
 * No bcmath or GMP required.
 */
final class Base36Encoder implements Encoder
{
    private const HALF_LENGTH = 7;

    public function encode(int $upper, int $lower): string
    {
        return str_pad(base_convert((string) $upper, 10, 36), self::HALF_LENGTH, '0', STR_PAD_LEFT)
             .str_pad(base_convert((string) $lower, 10, 36), self::HALF_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{0: int, 1: int} [upper32, lower32]
     */
    public function decode(string $s): array
    {
        return [
            (int) base_convert(substr($s, 0, self::HALF_LENGTH), 36, 10),
            (int) base_convert(substr($s, self::HALF_LENGTH, self::HALF_LENGTH), 36, 10),
        ];
    }
}
