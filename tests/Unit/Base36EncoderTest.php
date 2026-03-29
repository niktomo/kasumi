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
        $this->encoder = new Base36Encoder;
    }

    /**
     * @param  int  $upper  上位32bit
     * @param  int  $lower  下位32bit
     */
    #[DataProvider('values')]
    public function test_encode_then_decode_returns_original(int $upper, int $lower, string $expected): void
    {
        // Act
        $encoded = $this->encoder->encode($upper, $lower);
        [$decUpper, $decLower] = $this->encoder->decode($encoded);

        // Assert
        $this->assertSame($expected, $encoded, "({$upper}, {$lower}) の base36 エンコード結果が正しいこと");
        $this->assertSame($upper, $decUpper, "decode した upper が元の {$upper} に戻ること");
        $this->assertSame($lower, $decLower, "decode した lower が元の {$lower} に戻ること");
    }

    /** @return list<array{0: int, 1: int, 2: string}> */
    public static function values(): array
    {
        // upper(7chars) + lower(7chars) = 14chars
        return [
            [0, 0,          '00000000000000'],  // "0000000" + "0000000"
            [0, 1,          '00000000000001'],  // "0000000" + "0000001"
            [0, 9,          '00000000000009'],  // "0000000" + "0000009"
            [0, 10,         '0000000000000a'],  // "0000000" + "000000a"
            [0, 35,         '0000000000000z'],  // "0000000" + "000000z"
            [0, 36,         '00000000000010'],  // "0000000" + "0000010"
            [0, 12345,      '000000000009ix'],  // "0000000" + "00009ix"
            // 各halfの最大値 = 2^32-1 = 4294967295 = "1z141z3" in base36
            [0xFFFFFFFF, 0xFFFFFFFF, '1z141z31z141z3'],
        ];
    }

    #[DataProvider('lengthValues')]
    public function test_encoded_string_is_always_14_chars(int $upper, int $lower): void
    {
        // Act
        $encoded = $this->encoder->encode($upper, $lower);

        // Assert
        $this->assertSame(
            14,
            strlen($encoded),
            "({$upper}, {$lower}) のエンコード結果が常に14文字であること"
        );
    }

    /** @return list<array{0: int, 1: int}> */
    public static function lengthValues(): array
    {
        return [
            [0, 0],
            [0, 1],
            [0, 12345],
            [0x7FFFFFFF, 0xFFFFFFFF],  // PHP_INT_MAX
            [0xFFFFFFFF, 0xFFFFFFFF],  // 2^64-1
        ];
    }

    public function test_decode_all_zeros_returns_zero(): void
    {
        // Act
        [$upper, $lower] = $this->encoder->decode('00000000000000');

        // Assert
        $this->assertSame(0, $upper, '全ゼロ文字列を decode すると upper が 0 になること');
        $this->assertSame(0, $lower, '全ゼロ文字列を decode すると lower が 0 になること');
    }
}
