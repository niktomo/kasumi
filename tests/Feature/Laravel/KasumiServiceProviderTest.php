<?php

declare(strict_types=1);

namespace Kasumi\Tests\Feature\Laravel;

use Illuminate\Support\Facades\Config;
use Kasumi\ChecksumEncoder;
use Kasumi\Laravel\KasumiServiceProvider;
use Kasumi\Scrambler;
use Orchestra\Testbench\TestCase;

/**
 * Given: KasumiServiceProvider が登録されている
 * When:  コンテナから Scrambler を解決する
 * Then:  設定に応じた Scrambler が返るか、適切な例外が投げられる
 */
class KasumiServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [KasumiServiceProvider::class];
    }

    public function test_resolves_scrambler_with_valid_odd_salt(): void
    {
        // Given
        Config::set('kasumi.scramble_salt', 1234567891);

        // When
        $app      = $this->app ?? $this->fail('Application が初期化されていること');
        $scrambler = $app->make(Scrambler::class);

        // Then
        $this->assertInstanceOf(
            Scrambler::class,
            $scrambler,
            '有効な奇数 salt を設定すると Scrambler が解決されること'
        );
    }

    public function test_resolves_scrambler_with_checksum_encoder(): void
    {
        // Given
        Config::set('kasumi.scramble_salt', 1234567891);
        Config::set('kasumi.encoder', ChecksumEncoder::class);

        // When
        $app      = $this->app ?? $this->fail('Application が初期化されていること');
        $scrambler = $app->make(Scrambler::class);
        $encoded   = (string) $scrambler->scramble(12345);

        // Then
        $this->assertInstanceOf(
            Scrambler::class,
            $scrambler,
            'encoder に ChecksumEncoder を指定すると Scrambler が解決されること'
        );
        $this->assertSame(
            19,
            strlen($encoded),
            'ChecksumEncoder を使うとエンコード結果が19文字になること'
        );
    }

    public function test_throws_runtime_exception_when_encoder_is_invalid(): void
    {
        // Given: Encoder を実装しないクラスを指定
        Config::set('kasumi.scramble_salt', 1234567891);
        Config::set('kasumi.encoder', \stdClass::class);

        // Then
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/must implement/');

        // When
        $app = $this->app ?? $this->fail('Application が初期化されていること');
        $app->make(Scrambler::class);
    }

    public function test_throws_runtime_exception_when_salt_is_not_set(): void
    {
        // Given
        Config::set('kasumi.scramble_salt', null);

        // Then (expectException は Act の前に宣言する必要がある)
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/KASUMI_SCRAMBLE_SALT is not set/');

        // When
        $app = $this->app ?? $this->fail('Application が初期化されていること');
        $app->make(Scrambler::class);
    }

    public function test_throws_runtime_exception_when_salt_is_zero(): void
    {
        // Given: 0 は empty() で true になるため未設定扱い
        Config::set('kasumi.scramble_salt', 0);

        // Then (expectException は Act の前に宣言する必要がある)
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/KASUMI_SCRAMBLE_SALT is not set/');

        // When
        $app = $this->app ?? $this->fail('Application が初期化されていること');
        $app->make(Scrambler::class);
    }

    public function test_throws_invalid_argument_when_salt_is_even(): void
    {
        // Given: 偶数は Scrambler が拒否する
        Config::set('kasumi.scramble_salt', 4);

        // Then (expectException は Act の前に宣言する必要がある)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Salt must be odd/');

        // When
        $app = $this->app ?? $this->fail('Application が初期化されていること');
        $app->make(Scrambler::class);
    }
}
