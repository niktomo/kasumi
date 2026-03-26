<?php

declare(strict_types=1);

namespace Kasumi;

/**
 * A scrambled integer value with optional string encoding.
 * Implements Stringable — the string representation is delegated to an Encoder.
 */
final class ScrambledValue implements \Stringable
{
    public function __construct(
        private readonly int $value,
        private readonly Encoder $encoder,
    ) {}

    public function toInt(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->encoder->encode($this->value);
    }
}
