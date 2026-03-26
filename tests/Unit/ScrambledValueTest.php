<?php

declare(strict_types=1);

namespace Kasumi\Tests\Unit;

use Kasumi\Base36Encoder;
use Kasumi\Encoder;
use Kasumi\ScrambledValue;
use PHPUnit\Framework\TestCase;

class ScrambledValueTest extends TestCase
{
    public function test_to_int_returns_constructed_value(): void
    {
        // Arrange
        $value = new ScrambledValue(12345, new Base36Encoder());

        // Act
        $result = $value->toInt();

        // Assert
        $this->assertSame(12345, $result, 'toInt() がコンストラクタに渡した値をそのまま返すこと');
    }

    public function test_to_string_delegates_to_encoder(): void
    {
        // Arrange
        $encoder = new class implements Encoder {
            public function encode(int $n): string
            {
                return "encoded:{$n}";
            }

            public function decode(string $s): int
            {
                return 0;
            }
        };
        $value = new ScrambledValue(42, $encoder);

        // Act
        $result = (string) $value;

        // Assert
        $this->assertSame('encoded:42', $result, '__toString() が Encoder::encode() に委譲すること');
    }

    public function test_implements_stringable(): void
    {
        // Arrange
        $value = new ScrambledValue(1, new Base36Encoder());

        // Assert
        $this->assertInstanceOf(\Stringable::class, $value, 'Stringable インターフェースを実装していること');
    }

    public function test_to_int_with_zero(): void
    {
        // Arrange
        $value = new ScrambledValue(0, new Base36Encoder());

        // Act
        $result = $value->toInt();

        // Assert
        $this->assertSame(0, $result, 'toInt() が 0 を保持すること');
    }

    public function test_to_int_with_max_int(): void
    {
        // Arrange
        $value = new ScrambledValue(PHP_INT_MAX, new Base36Encoder());

        // Act
        $result = $value->toInt();

        // Assert
        $this->assertSame(PHP_INT_MAX, $result, 'toInt() が PHP_INT_MAX を保持すること');
    }
}
