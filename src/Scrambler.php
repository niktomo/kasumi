<?php

declare(strict_types=1);

namespace Kasumi;

/**
 * Reversible 63-bit integer scrambler — involutory (f(f(x)) = x).
 *
 * Algorithm (ported from https://cs.hatenablog.jp/entry/2013/06/19/135527):
 *   scramble(x) = inverseSalt × reverseBits63(salt × x mod 2^63) mod 2^63
 *
 * Proof of involution:
 *   f(f(x)) = inverseSalt × reverse(salt × inverseSalt × reverse(salt × x))
 *           = inverseSalt × reverse(reverse(salt × x))   (salt × inverseSalt ≡ 1 mod 2^63)
 *           = inverseSalt × (salt × x)
 *           = x
 *
 * Note: scramble(0) = 0 (trivial fixed point — avoid passing 0 if this matters).
 */
final class Scrambler
{
    private const MODULUS = Modulus::VALUE;

    public function __construct(
        private readonly ScrambleKey $key,
        private readonly Encoder $encoder = new Base36Encoder(),
    ) {}

    /**
     * Create from a salt value (e.g. loaded from config).
     * The salt must be an odd integer.
     */
    public static function fromSalt(int $salt, Encoder $encoder = new Base36Encoder()): self
    {
        return new self((new ScrambleKeyFactory())->create($salt), $encoder);
    }

    /**
     * Scramble or unscramble an integer (same operation — involutory).
     * Returns a ScrambledValue that stringifies via the injected Encoder.
     *
     * @throws \InvalidArgumentException if $n is negative
     */
    public function scramble(int $n): ScrambledValue
    {
        if ($n < 0) {
            throw new \InvalidArgumentException(
                "Value must be non-negative. Got {$n}."
            );
        }

        $salted   = $this->mulmod($n, $this->key->salt);
        $reversed = $this->reverseBits63($salted);
        $result   = $this->mulmod($reversed, $this->key->inverseSalt);

        return new ScrambledValue($result, $this->encoder);
    }

    private function mulmod(int $a, int $b): int
    {
        return (int) bcmod(bcmul((string) $a, (string) $b), self::MODULUS);
    }

    /**
     * Reverse the order of bits 0–62 (63-bit parallel bit reversal).
     *
     * Performs a full 64-bit parallel reversal, then logical-shifts right by 1
     * so that bit i of the input maps to bit (62 - i) of the output.
     * Result is always in [0, PHP_INT_MAX].
     */
    private function reverseBits63(int $v): int
    {
        $v = (($v >>  1) & 0x5555555555555555) | (($v & 0x5555555555555555) <<  1);
        $v = (($v >>  2) & 0x3333333333333333) | (($v & 0x3333333333333333) <<  2);
        $v = (($v >>  4) & 0x0F0F0F0F0F0F0F0F) | (($v & 0x0F0F0F0F0F0F0F0F) <<  4);
        $v = (($v >>  8) & 0x00FF00FF00FF00FF) | (($v & 0x00FF00FF00FF00FF) <<  8);
        $v = (($v >> 16) & 0x0000FFFF0000FFFF) | (($v & 0x0000FFFF0000FFFF) << 16);
        $v = (($v >> 32) & 0x00000000FFFFFFFF) | ($v                        << 32);

        return ($v >> 1) & PHP_INT_MAX;
    }
}
