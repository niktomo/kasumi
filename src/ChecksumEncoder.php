<?php

declare(strict_types=1);

namespace Kasumi;

/**
 * Encoder decorator that appends a 5-character base36 checksum to the inner encoding.
 *
 * The 5 checksum characters are split as follows:
 *   - 2-character prefix  — prepended to the output as a quick check
 *   - 3 filler characters — inserted at fixed body positions as noise
 *
 * Output length: 2 (prefix) + innerLength + 3 (fillers)
 * For Base36Encoder (innerLength = 14): total = 19 characters.
 *
 * Decoding rejects any string that was not produced by this encoder.
 *
 * Body layout (innerLength = 14 → bodyLength = 17):
 *   pos:  0  1  [2]  3  4  5  [6]  7  8  9  [10]  11 12 13 14 15 16
 *         i  i   F   i  i  i   F   i  i  i   F    i  i  i  i  i  i
 *   F = filler, i = inner char
 */
final class ChecksumEncoder implements Encoder
{
    /** Positions within the body where filler characters are inserted. */
    private const array FILLER_POSITIONS = [2, 6, 10];

    private const int PREFIX_LENGTH = 2;
    private const int FILLER_COUNT  = 3;

    /** 36^5 = 60,466,176 — number of distinct 5-char base36 strings. */
    private const int CHECKSUM_MOD = 60_466_176;

    private readonly int $innerLength;
    private readonly int $totalLength;

    public function __construct(private readonly Encoder $inner)
    {
        $this->innerLength = strlen($this->inner->encode(0, 0));
        $this->totalLength = self::PREFIX_LENGTH + $this->innerLength + self::FILLER_COUNT;
    }

    public function encode(int $upper, int $lower): string
    {
        $inner  = $this->inner->encode($upper, $lower);
        $check  = $this->computeCheck($inner);
        $prefix = substr($check, 0, self::PREFIX_LENGTH);
        $filler = str_split(substr($check, self::PREFIX_LENGTH));

        return $prefix . $this->buildBody($inner, $filler);
    }

    /**
     * @return array{0: int, 1: int}
     * @throws \InvalidArgumentException if the string was not produced by this encoder
     */
    public function decode(string $s): array
    {
        if (strlen($s) !== $this->totalLength) {
            throw new \InvalidArgumentException('Invalid encoded ID.');
        }

        $prefix = substr($s, 0, self::PREFIX_LENGTH);
        $body   = substr($s, self::PREFIX_LENGTH);

        [$inner, $filler] = $this->parseBody($body);

        if (!hash_equals($this->computeCheck($inner), $prefix . implode('', $filler))) {
            throw new \InvalidArgumentException('Invalid encoded ID.');
        }

        return $this->inner->decode($inner);
    }

    /**
     * Polynomial rolling hash mod 36^5, encoded as a 5-char base36 string.
     * Both the prefix and filler chars are derived from this single value,
     * so tampering either part invalidates the whole.
     */
    private function computeCheck(string $inner): string
    {
        $hash = 0;

        for ($i = 0, $len = strlen($inner); $i < $len; $i++) {
            $hash = ($hash * 37 + ord($inner[$i])) % self::CHECKSUM_MOD;
        }

        return str_pad(
            base_convert((string) $hash, 10, 36),
            self::PREFIX_LENGTH + self::FILLER_COUNT,
            '0',
            STR_PAD_LEFT,
        );
    }

    /** @param list<string> $filler */
    private function buildBody(string $inner, array $filler): string
    {
        $chars     = str_split($inner);
        $innerIdx  = 0;
        $fillerIdx = 0;
        $result    = [];

        for ($pos = 0, $len = $this->innerLength + self::FILLER_COUNT; $pos < $len; $pos++) {
            $result[] = in_array($pos, self::FILLER_POSITIONS, true)
                ? $filler[$fillerIdx++]
                : $chars[$innerIdx++];
        }

        return implode('', $result);
    }

    /**
     * @return array{0: string, 1: list<string>} [inner, filler]
     */
    private function parseBody(string $body): array
    {
        $inner  = [];
        $filler = [];

        for ($pos = 0, $len = strlen($body); $pos < $len; $pos++) {
            if (in_array($pos, self::FILLER_POSITIONS, true)) {
                $filler[] = $body[$pos];
            } else {
                $inner[] = $body[$pos];
            }
        }

        return [implode('', $inner), $filler];
    }
}
