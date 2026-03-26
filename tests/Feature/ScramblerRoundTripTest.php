<?php

declare(strict_types=1);

namespace Kasumi\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Kasumi\Base36Encoder;
use Kasumi\Laravel\KasumiServiceProvider;
use Kasumi\Scrambler;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Given: config に kasumi.scramble_salt が設定されている
 * When:  コンテナから Scrambler を解決し、整数を scramble する
 * Then:  同じ値をもう一度 scramble すると元の値に戻る（involution）
 */
class ScramblerRoundTripTest extends TestCase
{
    private Scrambler $scrambler;

    protected function getPackageProviders($app): array
    {
        return [KasumiServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Given: config に salt を設定
        Config::set('kasumi.scramble_salt', 1234567891);

        $this->scrambler = $this->app->make(Scrambler::class);
    }

    /** @param non-negative-int $original */
    #[DataProvider('values')]
    public function test_scramble_twice_returns_original(int $original): void
    {
        // When
        $scrambled   = $this->scrambler->scramble($original);
        $unscrambled = $this->scrambler->scramble($scrambled->toInt());

        // Then
        $this->assertSame(
            $original,
            $unscrambled->toInt(),
            "scramble(scramble({$original})) = {$original} となること"
        );
    }

    /** @param non-negative-int $original */
    #[DataProvider('values')]
    public function test_encoded_string_round_trips_via_decode(int $original): void
    {
        // When
        $encoded   = (string) $this->scrambler->scramble($original);
        $decoded   = (new Base36Encoder())->decode($encoded);
        $recovered = $this->scrambler->scramble($decoded)->toInt();

        // Then
        $this->assertMatchesRegularExpression(
            '/^[0-9a-z]+$/',
            $encoded,
            "エンコード結果が base36 文字列であること"
        );
        $this->assertSame(
            $original,
            $recovered,
            "base36 文字列を decode → unscramble すると元の値 {$original} に戻ること"
        );
    }

    /** @return list<array{0: non-negative-int}> */
    public static function values(): array
    {
        return [
            'one'         => [1],
            'typical ID'  => [12345],
            'large ID'    => [9999999999],
            'PHP_INT_MAX' => [PHP_INT_MAX],
        ];
    }
}
