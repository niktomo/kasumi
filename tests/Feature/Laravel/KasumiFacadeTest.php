<?php

declare(strict_types=1);

namespace Kasumi\Tests\Feature\Laravel;

use Illuminate\Support\Facades\Config;
use Kasumi\Laravel\Facades\Kasumi;
use Kasumi\Laravel\KasumiServiceProvider;
use Kasumi\ScrambledValue;
use Orchestra\Testbench\TestCase;

/**
 * Given: KasumiServiceProvider が登録され、有効な奇数 salt が設定されている
 * When:  Kasumi Facade 経由で scramble する
 * Then:  ScrambledValue が返り、involution が成立する
 */
class KasumiFacadeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [KasumiServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Given: config に salt を設定
        Config::set('kasumi.scramble_salt', 1234567891);
    }

    public function test_facade_returns_scrambled_value(): void
    {
        // When
        $result = Kasumi::scramble(12345);

        // Then
        $this->assertInstanceOf(
            ScrambledValue::class,
            $result,
            'Facade::scramble() が ScrambledValue を返すこと'
        );
    }

    public function test_facade_scramble_is_involutory(): void
    {
        // Arrange
        $original = 12345;

        // When
        $scrambled   = Kasumi::scramble($original)->toInt();
        $unscrambled = Kasumi::scramble($scrambled)->toInt();

        // Then
        $this->assertSame(
            $original,
            $unscrambled,
            'Facade 経由でも scramble(scramble(x)) = x となること'
        );
    }

    public function test_facade_scramble_produces_base36_string(): void
    {
        // When
        $result = (string) Kasumi::scramble(12345);

        // Then
        $this->assertMatchesRegularExpression(
            '/^[0-9a-z]{13}$/',
            $result,
            'Facade 経由のエンコード結果が 13 文字の base36 文字列であること'
        );
    }
}
