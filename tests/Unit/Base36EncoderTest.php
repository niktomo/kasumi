<?php

declare(strict_types=1);

namespace Kasumi\Tests\Unit;

use Kasumi\Base36Encoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class Base36EncoderTest extends TestCase
{
    private Base36Encoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new Base36Encoder();
    }

    /** @param non-negative-int $n */
    #[DataProvider('values')]
    public function test_encode_then_decode_returns_original(int $n, string $expected): void
    {
        // Act
        $encoded = $this->encoder->encode($n);
        $decoded = $this->encoder->decode($encoded);

        // Assert
        $this->assertSame($expected, $encoded, "{$n} の base36 エンコード結果が正しいこと");
        $this->assertSame($n, $decoded, "decode すると元の値 {$n} に戻ること");
    }

    /** @return list<array{0: non-negative-int, 1: string}> */
    public static function values(): array
    {
        return [
            [0,          '0000000000000'],
            [1,          '0000000000001'],
            [9,          '0000000000009'],
            [10,         '000000000000a'],
            [35,         '000000000000z'],
            [36,         '0000000000010'],
            [12345,      '00000000009ix'],
            [PHP_INT_MAX, '1y2p0ij32e8e7'],
        ];
    }

    /** @param non-negative-int $n */
    #[DataProvider('lengthValues')]
    public function test_encoded_string_is_always_13_chars(int $n): void
    {
        // Act
        $encoded = $this->encoder->encode($n);

        // Assert
        $this->assertSame(
            13,
            strlen($encoded),
            "{$n} のエンコード結果が常に13文字であること"
        );
    }

    /** @return list<array{0: non-negative-int}> */
    public static function lengthValues(): array
    {
        return [
            [0],
            [1],
            [12345],
            [PHP_INT_MAX],
        ];
    }

    public function test_decode_empty_string_returns_zero(): void
    {
        // Act
        $result = $this->encoder->decode('');

        // Assert
        $this->assertSame(0, $result, '空文字列を decode すると 0 が返ること（ltrim + フォールバック動作）');
    }

    public function test_decode_all_zeros_returns_zero(): void
    {
        // Act
        $result = $this->encoder->decode('0000000000000');

        // Assert
        $this->assertSame(0, $result, '全ゼロ文字列を decode すると 0 が返ること');
    }
}
