<?php

declare(strict_types=1);

namespace Kasumi\Tests\Unit;

use Kasumi\ScrambleKey;
use Kasumi\ScrambleKeyFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScrambleKeyFactoryTest extends TestCase
{
    private ScrambleKeyFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ScrambleKeyFactory();
    }

    public function test_creates_scramble_key(): void
    {
        // Act
        $key = $this->factory->create();

        // Assert
        $this->assertInstanceOf(ScrambleKey::class, $key, 'ScrambleKey が返ること');
    }

    public function test_salt_is_odd(): void
    {
        // Act
        $key = $this->factory->create();

        // Assert
        $this->assertSame(1, $key->salt % 2, 'salt が奇数であること');
    }

    /** @param positive-int $salt */
    #[DataProvider('knownOddSalts')]
    public function test_inverse_satisfies_modular_identity(int $salt): void
    {
        // Arrange
        $key = $this->factory->create($salt);

        // Act
        $product = bcmod(bcmul((string) $key->salt, (string) $key->inverseSalt), bcpow('2', '63'));

        // Assert
        $this->assertSame(
            '1',
            $product,
            "salt={$salt} のとき salt * inverseSalt ≡ 1 (mod 2^63) を満たすこと"
        );
    }

    /** @return list<array{0: positive-int}> */
    public static function knownOddSalts(): array
    {
        return [
            [1],
            [3],
            [7],
            [1234567891],
            [PHP_INT_MAX],
        ];
    }

    public function test_throws_for_even_salt(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Salt must be odd/');

        // Act
        $this->factory->create(4);
    }
}
