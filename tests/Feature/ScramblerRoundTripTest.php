<?php

declare(strict_types=1);

namespace Kasumi\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Kasumi\Base36Encoder;
use Kasumi\Laravel\KasumiServiceProvider;
use Kasumi\ScrambledValue;
use Kasumi\Scrambler;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Given: config に kasumi.scramble_salt が設定されている
 * When:  コンテナから Scrambler を解決し、整数を scramble する
 * Then:  同じ ScrambledValue をもう一度 scramble すると元の値に戻る（involution）
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

        $app = $this->app ?? $this->fail('Application not initialized.');
        $this->scrambler = $app->make(Scrambler::class);
    }

    /** @param non-negative-int $original */
    #[DataProvider('values')]
    public function test_scramble_twice_returns_original(int $original): void
    {
        // When — ScrambledValue を直接渡すことで toInt() の overflow を回避
        $scrambled = $this->scrambler->scramble($original);
        $unscrambled = $this->scrambler->scramble($scrambled);

        // Then
        $this->assertSame(
            $original,
            $unscrambled->toInt(),
            "scramble(scramble({$original})) = {$original} となること"
        );
    }

    /** @param non-negative-int $original */
    #[DataProvider('values')]
    public function test_encoded_string_round_trips_via_scrambled_value(int $original): void
    {
        // When
        $scrambled = $this->scrambler->scramble($original);
        $encoded = (string) $scrambled;
        $recovered = $this->scrambler->scramble($scrambled)->toInt();

        // Then
        $this->assertMatchesRegularExpression(
            '/^[0-9a-z]{14}$/',
            $encoded,
            'エンコード結果が14文字の base36 文字列であること'
        );
        $this->assertSame(
            $original,
            $recovered,
            "ScrambledValue を再度 scramble すると元の値 {$original} に戻ること"
        );
    }

    /** @param non-negative-int $original */
    #[DataProvider('values')]
    public function test_decode_then_scramble_returns_original(int $original): void
    {
        // When — 文字列経由での往復: encode → decode → ScrambledValue → scramble
        $encoded = (string) $this->scrambler->scramble($original);
        $encoder = new Base36Encoder;
        [$upper, $lower] = $encoder->decode($encoded);
        $fromDecoded = $this->scrambler->scramble(new ScrambledValue($upper, $lower, $encoder));
        $recovered = $fromDecoded->toInt();

        // Then
        $this->assertSame(
            $original,
            $recovered,
            "base36 文字列を decode → ScrambledValue → scramble すると元の値 {$original} に戻ること"
        );
    }

    /** @return array<string, array{0: non-negative-int}> */
    public static function values(): array
    {
        return [
            'one' => [1],
            'typical ID' => [12345],
            'large ID' => [9999999999],
            'PHP_INT_MAX' => [PHP_INT_MAX],
        ];
    }
}
