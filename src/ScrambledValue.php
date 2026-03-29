<?php

declare(strict_types=1);

namespace Kasumi;

/**
 * A scrambled 64-bit value stored as two 32-bit halves.
 * Implements Stringable — the string representation is delegated to an Encoder.
 *
 * toInt() returns the combined value as a PHP int when it fits in [0, PHP_INT_MAX].
 * If the scrambled value exceeds PHP_INT_MAX, toInt() throws OverflowException.
 * To unscramble, pass this object directly to Scrambler::scramble() — no toInt() needed.
 */
final class ScrambledValue implements \Stringable
{
    public function __construct(
        public readonly int $upper,
        public readonly int $lower,
        private readonly Encoder $encoder,
    ) {}

    /**
     * @throws \OverflowException if the value exceeds PHP_INT_MAX
     */
    public function toInt(): int
    {
        $result = ($this->upper << 32) | $this->lower;

        if ($result < 0) {
            throw new \OverflowException(
                'Scrambled value exceeds PHP_INT_MAX and cannot be represented as a PHP integer. '
                .'Pass this ScrambledValue directly to Scrambler::scramble() to unscramble.'
            );
        }

        return $result;
    }

    public function __toString(): string
    {
        return $this->encoder->encode($this->upper, $this->lower);
    }
}
