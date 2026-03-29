<?php

declare(strict_types=1);

namespace Kasumi\Tests\Unit;

use Kasumi\Base36Encoder;
use Kasumi\ScrambledValue;
use Kasumi\Scrambler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScramblerTest extends TestCase
{
    private const SALT = 3;

    private Scrambler $scrambler;

    protected function setUp(): void
    {
        $this->scrambler = Scrambler::fromSalt(self::SALT);
    }

    public function test_scramble_returns_scrambled_value(): void
    {
        // Act
        $result = $this->scrambler->scramble(42);

        // Assert
        $this->assertInstanceOf(ScrambledValue::class, $result, 'ScrambledValue が返ること');
    }

    /** @param non-negative-int $n */
    #[DataProvider('integers')]
    public function test_scramble_is_involutory(int $n): void
    {
        // Act — ScrambledValue を直接渡すことで toInt() の overflow を回避
        $result = $this->scrambler->scramble($this->scrambler->scramble($n))->toInt();

        // Assert
        $this->assertSame(
            $n,
            $result,
            "scramble(scramble({$n})) = {$n} となること（involution）"
        );
    }

    /** @return array<string, array{0: non-negative-int}> */
    public static function integers(): array
    {
        return [
            'zero' => [0],
            'one' => [1],
            'typical' => [42],
            'large' => [1000000],
            '32bit all 1s' => [0xFFFFFFFF],   // lower=0xFFFFFFFF, upper=0
            '32bit high-bit 0' => [0x7FFFFFFF],   // lower=0x7FFFFFFF, upper=0
            '33bit all 1s' => [0x1FFFFFFFF],  // lower=0xFFFFFFFF, upper=1（境界またぎ）
            '33bit low-bit 0' => [0x1FFFFFFFE],  // lower=0xFFFFFFFE, upper=1
            'max' => [PHP_INT_MAX],
        ];
    }

    /** @param positive-int $n */
    #[DataProvider('nonFixedPointIntegers')]
    public function test_scramble_produces_different_value(int $n): void
    {
        // Arrange — n < 2^32 なので identity encoding は encode(0, n)
        $encoder = new Base36Encoder;
        $identityStr = $encoder->encode(0, $n);

        // Act
        $scrambledStr = (string) $this->scrambler->scramble($n);

        // Assert
        $this->assertNotSame($identityStr, $scrambledStr, "scramble({$n}) が元の値と異なること");
    }

    /** @return list<array{0: positive-int}> */
    public static function nonFixedPointIntegers(): array
    {
        return [
            [1],
            [12345],
            [1_000_000],
        ];
    }

    public function test_scramble_zero_is_fixed_point(): void
    {
        // Act
        $result = $this->scrambler->scramble(0)->toInt();

        // Assert
        $this->assertSame(0, $result, 'scramble(0) = 0 （trivial fixed point）であること');
    }

    public function test_scramble_is_deterministic(): void
    {
        // Arrange
        $n = 99999;

        // Act
        $first = (string) $this->scrambler->scramble($n);
        $second = (string) $this->scrambler->scramble($n);

        // Assert
        $this->assertSame($first, $second, '同じ入力に対して常に同じ値を返すこと');
    }

    public function test_scramble_throws_for_negative(): void
    {
        // Assert (expectException は Act の前に宣言する必要がある)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Value must be non-negative/');

        // Act
        $this->scrambler->scramble(-1);
    }

    public function test_involutory_with_another_salt(): void
    {
        // Arrange
        $scrambler = Scrambler::fromSalt(1234567891);
        $original = 7654321;

        // Act
        $result = $scrambler->scramble($scrambler->scramble($original))->toInt();

        // Assert
        $this->assertSame(
            $original,
            $result,
            '別の奇数 salt でも involution が成立すること'
        );
    }

    public function test_to_string_uses_encoder(): void
    {
        // Arrange
        $scrambler = Scrambler::fromSalt(self::SALT, new Base36Encoder);

        // Act
        $result = $scrambler->scramble(12345);

        // Assert
        $this->assertMatchesRegularExpression(
            '/^[0-9a-z]{14}$/',
            (string) $result,
            'Base36Encoder を使うと 14文字の 0-9a-z の文字列になること'
        );
    }

    public function test_scramble_accepts_scrambled_value_as_input(): void
    {
        // Arrange
        $original = 12345;

        // Act
        $scrambled = $this->scrambler->scramble($original);
        $unscrambled = $this->scrambler->scramble($scrambled);

        // Assert
        $this->assertSame(
            $original,
            $unscrambled->toInt(),
            'ScrambledValue を引数に渡すと元の値に戻ること'
        );
    }
}
