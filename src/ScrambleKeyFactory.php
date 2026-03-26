<?php

declare(strict_types=1);

namespace Kasumi;

/**
 * Generates a ScrambleKey by picking a random odd salt
 * and computing its modular multiplicative inverse mod 2^63.
 *
 * Inverse computation uses Newton–Raphson lifting:
 *   b = salt; repeat 62 times: b = b * (2 - salt * b) mod 2^63
 * Each iteration doubles the number of correct bits.
 */
final class ScrambleKeyFactory
{
    private const BITS = 63;

    private const MODULUS = Modulus::VALUE;

    public function create(?int $salt = null): ScrambleKey
    {
        $a = $salt ?? $this->randomOdd();

        if ($a % 2 === 0) {
            throw new \InvalidArgumentException("Salt must be odd, got {$a}.");
        }

        $b = $this->computeInverse($a);

        return new ScrambleKey($a, $b);
    }

    private function randomOdd(): int
    {
        return random_int(1, PHP_INT_MAX) | 1;
    }

    private function computeInverse(int $a): int
    {
        $b = $a;

        for ($i = 0; $i < self::BITS - 1; $i++) {
            $b = $this->mod63(bcmul((string) $b, bcsub('2', bcmul((string) $a, (string) $b))));
        }

        return $b;
    }

    private function mod63(string $n): int
    {
        $result = bcmod($n, self::MODULUS);

        if ($result[0] === '-') {
            $result = bcadd($result, self::MODULUS);
        }

        return (int) $result;
    }
}
