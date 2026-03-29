<?php

declare(strict_types=1);

namespace Kasumi;

/**
 * Reversible 64-bit integer scrambler — involutory (f(f(x)) = x).
 *
 * Splits the input into upper 32 bits and lower 32 bits,
 * scrambles each independently using mulmod32 (no bcmath required).
 *
 * Algorithm per half (ported from https://cs.hatenablog.jp/entry/2013/06/19/135527):
 *   scramble(x) = inverseSalt × reverseBits(salt × x mod 2^32) mod 2^32
 *
 * Multiplication mod 2^32 uses 16-bit splitting to avoid overflow:
 *   mulmod32(a, b) = ((aHi×bLo + aLo×bHi & 0xFFFF) << 16 + aLo×bLo) & 0xFFFFFFFF
 *
 * Input range:  [0, PHP_INT_MAX] (63-bit non-negative integers)
 * Output range: [0, 2^64-1] — may exceed PHP_INT_MAX; use ScrambledValue::__toString()
 *               to get the base36 representation, or pass the ScrambledValue back to
 *               scramble() to unscramble (always returns a value within [0, PHP_INT_MAX]).
 *
 * Note: scramble(0) = 0 (trivial fixed point — avoid passing 0 if this matters).
 */
final class Scrambler
{
    private const MASK32 = 0xFFFFFFFF; // 2^32 - 1

    private readonly int $salt32;

    private readonly int $inverseSalt32;

    public function __construct(
        int $salt,
        private readonly Encoder $encoder = new Base36Encoder,
    ) {
        if ($salt % 2 === 0) {
            throw new \InvalidArgumentException("Salt must be odd, got {$salt}.");
        }

        $this->salt32 = $salt & self::MASK32;
        $this->inverseSalt32 = $this->computeInverse32($this->salt32);
    }

    /**
     * Create from a salt value (e.g. loaded from config).
     * The salt must be an odd integer.
     */
    public static function fromSalt(int $salt, Encoder $encoder = new Base36Encoder): self
    {
        return new self($salt, $encoder);
    }

    /**
     * Scramble an integer, or unscramble a previously scrambled ScrambledValue.
     * The operation is involutory: scramble(scramble(x)) = x.
     *
     * Pass an int to scramble; pass a ScrambledValue to unscramble without needing toInt().
     *
     * @throws \InvalidArgumentException if $n is a negative integer
     */
    public function scramble(int|ScrambledValue $n): ScrambledValue
    {
        if ($n instanceof ScrambledValue) {
            $upper = $n->upper;
            $lower = $n->lower;
        } else {
            if ($n < 0) {
                throw new \InvalidArgumentException(
                    "Value must be non-negative. Got {$n}."
                );
            }
            $upper = ($n >> 32) & self::MASK32;
            $lower = $n & self::MASK32;
        }

        return new ScrambledValue(
            $this->scramble32($upper),
            $this->scramble32($lower),
            $this->encoder,
        );
    }

    private function scramble32(int $v): int
    {
        $salted = $this->mulmod32($this->salt32, $v);
        $reversed = $this->reverseBits32($salted);

        return $this->mulmod32($this->inverseSalt32, $reversed);
    }

    /**
     * Multiply two 32-bit values mod 2^32 using 16-bit splitting.
     * Avoids overflow of PHP's 63-bit integer.
     */
    private function mulmod32(int $a, int $b): int
    {
        $aLo = $a & 0xFFFF;
        $aHi = ($a >> 16) & 0xFFFF;
        $bLo = $b & 0xFFFF;
        $bHi = ($b >> 16) & 0xFFFF;
        $mid = ($aHi * $bLo + $aLo * $bHi) & 0xFFFF;

        return (($mid << 16) + $aLo * $bLo) & self::MASK32;
    }

    private function reverseBits32(int $v): int
    {
        $v = (($v >> 1) & 0x55555555) | (($v & 0x55555555) << 1);
        $v = (($v >> 2) & 0x33333333) | (($v & 0x33333333) << 2);
        $v = (($v >> 4) & 0x0F0F0F0F) | (($v & 0x0F0F0F0F) << 4);
        $v = (($v >> 8) & 0x00FF00FF) | (($v & 0x00FF00FF) << 8);
        $v = ($v >> 16) | (($v & 0x0000FFFF) << 16);

        return $v & self::MASK32;
    }

    /**
     * Compute modular inverse of $a mod 2^32 via Newton–Raphson lifting.
     * Requires $a to be odd. Runs 31 iterations (correct mod 2^32).
     */
    private function computeInverse32(int $a): int
    {
        $b = $a & self::MASK32;

        for ($i = 0; $i < 31; $i++) {
            $sub = (2 - $this->mulmod32($a, $b) + self::MASK32 + 1) & self::MASK32;
            $b = $this->mulmod32($b, $sub);
        }

        return $b;
    }
}
