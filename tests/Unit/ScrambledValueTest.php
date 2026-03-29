<?php

declare(strict_types=1);

namespace Kasumi\Tests\Unit;

use Kasumi\Base36Encoder;
use Kasumi\Encoder;
use Kasumi\ScrambledValue;
use PHPUnit\Framework\TestCase;

class ScrambledValueTest extends TestCase
{
    public function test_to_int_returns_combined_value(): void
    {
        // Arrange — (upper=0, lower=12345) → 0 << 32 | 12345 = 12345
        $value = new ScrambledValue(0, 12345, new Base36Encoder);

        // Act
        $result = $value->toInt();

        // Assert
        $this->assertSame(12345, $result, 'toInt() が upper<<32|lower の値を返すこと');
    }

    public function test_to_int_with_zero(): void
    {
        // Arrange
        $value = new ScrambledValue(0, 0, new Base36Encoder);

        // Act
        $result = $value->toInt();

        // Assert
        $this->assertSame(0, $result, 'toInt() が 0 を返すこと');
    }

    public function test_to_int_with_max_int(): void
    {
        // Arrange — PHP_INT_MAX = 0x7FFFFFFF_FFFFFFFF
        $value = new ScrambledValue(0x7FFFFFFF, 0xFFFFFFFF, new Base36Encoder);

        // Act
        $result = $value->toInt();

        // Assert
        $this->assertSame(PHP_INT_MAX, $result, 'toInt() が PHP_INT_MAX を返すこと');
    }

    public function test_to_int_throws_when_upper_exceeds_31_bits(): void
    {
        // Arrange — upper = 2^31 → (2^31 << 32) = PHP_INT_MIN（負になる）
        $value = new ScrambledValue(0x80000000, 0, new Base36Encoder);

        // Assert
        $this->expectException(\OverflowException::class);

        // Act
        $value->toInt();
    }

    public function test_to_string_delegates_to_encoder(): void
    {
        // Arrange
        $encoder = new class implements Encoder
        {
            public function encode(int $upper, int $lower): string
            {
                return "encoded:{$upper},{$lower}";
            }

            public function decode(string $s): array
            {
                return [0, 0];
            }
        };
        $value = new ScrambledValue(1, 42, $encoder);

        // Act
        $result = (string) $value;

        // Assert
        $this->assertSame('encoded:1,42', $result, '__toString() が Encoder::encode(upper, lower) に委譲すること');
    }

    public function test_implements_stringable(): void
    {
        // Arrange
        $value = new ScrambledValue(0, 1, new Base36Encoder);

        // Assert
        $this->assertInstanceOf(\Stringable::class, $value, 'Stringable インターフェースを実装していること');
    }

    public function test_upper_and_lower_are_publicly_readable(): void
    {
        // Arrange
        $value = new ScrambledValue(123, 456, new Base36Encoder);

        // Assert
        $this->assertSame(123, $value->upper, 'upper プロパティが読み取れること');
        $this->assertSame(456, $value->lower, 'lower プロパティが読み取れること');
    }
}
