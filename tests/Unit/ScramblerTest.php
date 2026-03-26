<?php

declare(strict_types=1);

namespace Kasumi\Tests\Unit;

use Kasumi\Base36Encoder;
use Kasumi\ScrambleKey;
use Kasumi\ScrambleKeyFactory;
use Kasumi\Scrambler;
use Kasumi\ScrambledValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScramblerTest extends TestCase
{
    // salt=3, inverseSalt: 3 * inv ≡ 1 mod 2^63
    private const int SALT         = 3;

    private const int INVERSE_SALT = 3074457345618258603;

    private Scrambler $scrambler;

    protected function setUp(): void
    {
        $key = new ScrambleKey(self::SALT, self::INVERSE_SALT);
        $this->scrambler = new Scrambler($key);
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
        // Act
        $result = $this->scrambler->scramble($this->scrambler->scramble($n)->toInt())->toInt();

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
            'zero'    => [0],
            'one'     => [1],
            'typical' => [42],
            'large'   => [1000000],
            'max'     => [PHP_INT_MAX],
        ];
    }

    /** @param positive-int $n */
    #[DataProvider('nonFixedPointIntegers')]
    public function test_scramble_produces_different_value(int $n): void
    {
        // Act
        $scrambled = $this->scrambler->scramble($n)->toInt();

        // Assert
        $this->assertNotSame($n, $scrambled, "scramble({$n}) が元の値と異なること");
    }

    /** @return list<array{0: positive-int}> */
    public static function nonFixedPointIntegers(): array
    {
        return [
            [1],
            [12345],
            [1_000_000],
            [PHP_INT_MAX],
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
        $first  = $this->scrambler->scramble($n)->toInt();
        $second = $this->scrambler->scramble($n)->toInt();

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

    public function test_involutory_with_generated_key(): void
    {
        // Arrange
        $scrambler = Scrambler::fromSalt((new ScrambleKeyFactory())->create()->salt);
        $original  = 7654321;

        // Act
        $result = $scrambler->scramble($scrambler->scramble($original)->toInt())->toInt();

        // Assert
        $this->assertSame(
            $original,
            $result,
            'ScrambleKeyFactory で生成したキーでも involution が成立すること'
        );
    }

    public function test_to_string_uses_encoder(): void
    {
        // Arrange
        $scrambler = Scrambler::fromSalt(self::SALT, new Base36Encoder());

        // Act
        $result = $scrambler->scramble(12345);

        // Assert
        $this->assertMatchesRegularExpression(
            '/^[0-9a-z]+$/',
            (string) $result,
            'Base36Encoder を使うと 0-9a-z の文字列になること'
        );
        $this->assertSame(
            $result->toInt(),
            (new Base36Encoder())->decode((string) $result),
            'decode すると toInt() と一致すること'
        );
    }
}
