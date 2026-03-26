<?php

declare(strict_types=1);

namespace Kasumi\Tests\Feature\Laravel;

use Kasumi\Laravel\KasumiServiceProvider;
use Orchestra\Testbench\TestCase;

class SaltGenerateCommandTest extends TestCase
{
    private string $envPath;

    protected function getPackageProviders($app): array
    {
        return [KasumiServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = $this->app->environmentFilePath();
        file_put_contents($this->envPath, '');
    }

    protected function tearDown(): void
    {
        unlink($this->envPath);

        parent::tearDown();
    }

    public function test_writes_salt_to_env_file(): void
    {
        // When
        $this->artisan('kasumi:salt:generate')->assertSuccessful();

        // Then
        $content = file_get_contents($this->envPath);
        assert(is_string($content));
        $this->assertMatchesRegularExpression(
            '/^KASUMI_SCRAMBLE_SALT=\d+$/m',
            $content,
            '.env に KASUMI_SCRAMBLE_SALT=<数字> が書き込まれること'
        );
    }

    public function test_written_salt_is_odd(): void
    {
        // When
        $this->artisan('kasumi:salt:generate')->assertSuccessful();

        // Then
        $content = file_get_contents($this->envPath);
        assert(is_string($content));
        $matched = preg_match('/^KASUMI_SCRAMBLE_SALT=(\d+)$/m', $content, $matches);
        $this->assertSame(1, $matched, 'KASUMI_SCRAMBLE_SALT のパターンが .env に存在すること');
        $this->assertSame(
            1,
            (int) $matches[1] % 2,
            '生成された salt が奇数であること'
        );
    }

    public function test_show_option_displays_salt_without_writing(): void
    {
        // When
        $this->artisan('kasumi:salt:generate --show')->assertSuccessful();

        // Then
        $content = file_get_contents($this->envPath);
        assert(is_string($content));
        $this->assertStringNotContainsString(
            'KASUMI_SCRAMBLE_SALT',
            $content,
            '--show オプションでは .env を変更しないこと'
        );
    }

    public function test_force_option_overwrites_existing_salt(): void
    {
        // Given
        $oldSalt = '1234567891';
        file_put_contents($this->envPath, 'KASUMI_SCRAMBLE_SALT=' . $oldSalt . PHP_EOL);

        // When
        $this->artisan('kasumi:salt:generate --force')->assertSuccessful();

        // Then
        $content = file_get_contents($this->envPath);
        assert(is_string($content));

        $lines = array_filter(explode(PHP_EOL, $content), fn ($l) => str_starts_with($l, 'KASUMI_SCRAMBLE_SALT='));
        $this->assertCount(1, $lines, 'KASUMI_SCRAMBLE_SALT のエントリが1つだけであること');

        $matched = preg_match('/^KASUMI_SCRAMBLE_SALT=(\d+)$/m', $content, $matches);
        $this->assertSame(1, $matched, 'KASUMI_SCRAMBLE_SALT のパターンが .env に存在すること');
        $this->assertNotSame($oldSalt, $matches[1], '--force オプションで既存の値が新しい値に上書きされること');
    }
}
