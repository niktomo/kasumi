<?php

declare(strict_types=1);

namespace Kasumi\Tests\Unit;

use Kasumi\Base36Encoder;
use Kasumi\ChecksumEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChecksumEncoderTest extends TestCase
{
    private ChecksumEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new ChecksumEncoder(new Base36Encoder());
    }

    // -------------------------------------------------------------------------
    // 出力長
    // -------------------------------------------------------------------------

    public function test_encoded_string_is_19_chars(): void
    {
        // Act
        $encoded = $this->encoder->encode(0, 12345);

        // Assert
        $this->assertSame(
            19,
            strlen($encoded),
            'Base36Encoder(14文字) をラップした ChecksumEncoder の出力が常に19文字であること'
        );
    }

    /**
     * @param int $upper
     * @param int $lower
     */
    #[DataProvider('roundTripValues')]
    public function test_encoded_string_is_always_19_chars(int $upper, int $lower): void
    {
        // Act
        $encoded = $this->encoder->encode($upper, $lower);

        // Assert
        $this->assertSame(
            19,
            strlen($encoded),
            "({$upper}, {$lower}) のエンコード結果が常に19文字であること"
        );
    }

    // -------------------------------------------------------------------------
    // ラウンドトリップ
    // -------------------------------------------------------------------------

    /**
     * @param int $upper
     * @param int $lower
     */
    #[DataProvider('roundTripValues')]
    public function test_encode_then_decode_returns_original(int $upper, int $lower): void
    {
        // Act
        $encoded          = $this->encoder->encode($upper, $lower);
        [$decUpper, $decLower] = $this->encoder->decode($encoded);

        // Assert
        $this->assertSame($upper, $decUpper, "decode した upper が元の {$upper} に戻ること");
        $this->assertSame($lower, $decLower, "decode した lower が元の {$lower} に戻ること");
    }

    /** @return list<array{0: int, 1: int}> */
    public static function roundTripValues(): array
    {
        return [
            [0, 0],
            [0, 1],
            [0, 12345],
            [0, 0xFFFFFFFF],
            [1, 0],
            [0xFFFFFFFF, 0xFFFFFFFF],
            [0x7FFFFFFF, 0xFFFFFFFF],
        ];
    }

    // -------------------------------------------------------------------------
    // 決定論的
    // -------------------------------------------------------------------------

    public function test_encode_is_deterministic(): void
    {
        // Act
        $first  = $this->encoder->encode(0, 99999);
        $second = $this->encoder->encode(0, 99999);

        // Assert
        $this->assertSame($first, $second, '同じ入力は常に同じエンコード結果になること');
    }

    // -------------------------------------------------------------------------
    // 異なる入力 → 異なる出力
    // -------------------------------------------------------------------------

    public function test_different_inputs_produce_different_encodings(): void
    {
        // Act
        $a = $this->encoder->encode(0, 1);
        $b = $this->encoder->encode(0, 2);

        // Assert
        $this->assertNotSame($a, $b, '異なる入力は異なるエンコード結果を生成すること');
    }

    // -------------------------------------------------------------------------
    // decode: 不正な長さ
    // -------------------------------------------------------------------------

    public function test_decode_throws_on_wrong_length(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid encoded ID.');

        // Act — 18文字（1文字短い）
        $this->encoder->decode('0000000000000000000');
    }

    public function test_decode_throws_on_too_short_string(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid encoded ID.');

        // Act
        $this->encoder->decode('abc');
    }

    public function test_decode_throws_on_empty_string(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid encoded ID.');

        // Act
        $this->encoder->decode('');
    }

    // -------------------------------------------------------------------------
    // decode: チェックサム改ざん検出
    // -------------------------------------------------------------------------

    public function test_decode_throws_on_tampered_prefix(): void
    {
        // Arrange
        $valid = $this->encoder->encode(0, 42);
        // 先頭2文字（プレフィックス）を書き換え
        $tampered = 'zz' . substr($valid, 2);

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid encoded ID.');

        // Act
        $this->encoder->decode($tampered);
    }

    public function test_decode_throws_on_tampered_filler_at_position_2(): void
    {
        // Arrange
        $valid = $this->encoder->encode(0, 42);
        // ボディ内 pos=2（フィラー位置）を書き換え: prefix(2) + body[2] = index 4
        $index    = 4;
        $tampered = substr($valid, 0, $index) . ($valid[$index] === 'z' ? '0' : 'z') . substr($valid, $index + 1);

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid encoded ID.');

        // Act
        $this->encoder->decode($tampered);
    }

    public function test_decode_throws_on_tampered_body_inner_char(): void
    {
        // Arrange
        $valid = $this->encoder->encode(0, 12345);
        // ボディ内 pos=0（内部エンコード文字）を書き換え: prefix(2) + body[0] = index 2
        $index    = 2;
        $tampered = substr($valid, 0, $index) . ($valid[$index] === 'z' ? '0' : 'z') . substr($valid, $index + 1);

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid encoded ID.');

        // Act
        $this->encoder->decode($tampered);
    }
}
