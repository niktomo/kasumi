<?php

declare(strict_types=1);

namespace Kasumi;

/**
 * Immutable value object holding a salt and its modular inverse mod 2^63.
 *
 *   salt * inverseSalt ≡ 1 (mod 2^63)
 */
final readonly class ScrambleKey
{
    public function __construct(
        public readonly int $salt,
        public readonly int $inverseSalt,
    ) {}
}
