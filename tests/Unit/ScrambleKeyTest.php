<?php

declare(strict_types=1);

namespace Kasumi\Tests\Unit;

use Kasumi\ScrambleKey;
use PHPUnit\Framework\TestCase;

class ScrambleKeyTest extends TestCase
{
    public function test_holds_salt_and_inverse_salt(): void
    {
        // Arrange
        $salt        = 1234567891;
        $inverseSalt = 9876543211;

        // Act
        $key = new ScrambleKey($salt, $inverseSalt);

        // Assert
        $this->assertSame($salt, $key->salt, 'salt が保持されること');
        $this->assertSame($inverseSalt, $key->inverseSalt, 'inverseSalt が保持されること');
    }
}
