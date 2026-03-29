<?php

declare(strict_types=1);

namespace Kasumi;

/**
 * Converts a scrambled 64-bit value (upper 32 bits + lower 32 bits) to/from a string.
 */
interface Encoder
{
    /**
     * Encode a 64-bit value stored as two 32-bit halves into a string.
     *
     * @param  int  $upper  Upper 32 bits (0 to 2^32-1)
     * @param  int  $lower  Lower 32 bits (0 to 2^32-1)
     */
    public function encode(int $upper, int $lower): string;

    /**
     * Decode a string back into the two 32-bit halves.
     *
     * @return array{0: int, 1: int} [upper32, lower32]
     */
    public function decode(string $s): array;
}
