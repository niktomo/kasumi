<?php

declare(strict_types=1);

namespace Kasumi;

/**
 * Converts a scrambled integer to/from a string representation.
 */
interface Encoder
{
    public function encode(int $n): string;

    public function decode(string $s): int;
}
