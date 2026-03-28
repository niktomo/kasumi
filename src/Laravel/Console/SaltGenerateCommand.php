<?php

declare(strict_types=1);

namespace Kasumi\Laravel\Console;

use Illuminate\Console\Command;
class SaltGenerateCommand extends Command
{
    protected $signature = 'kasumi:salt:generate
                            {--show : Display the salt instead of writing to .env}
                            {--force : Overwrite an existing salt without confirmation}';

    protected $description = 'Generate a new Kasumi scramble salt and write it to .env';

    public function handle(): int
    {
        $salt = random_int(1, 0xFFFFFFFF) | 1;

        if ($this->option('show')) {
            $this->line('KASUMI_SCRAMBLE_SALT=' . $salt);

            return self::SUCCESS;
        }

        $envPath = $this->laravel->basePath('.env');

        if (! file_exists($envPath)) {
            $this->error('.env file not found: ' . $envPath);

            return self::FAILURE;
        }

        $current = file_get_contents($envPath);
        assert(is_string($current));

        if (str_contains($current, 'KASUMI_SCRAMBLE_SALT=')) {
            $this->warn('WARNING: Changing the salt will invalidate all existing scrambled values.');

            if (! $this->option('force')) {
                if (! $this->confirm('KASUMI_SCRAMBLE_SALT already exists in .env. Overwrite?')) {
                    $this->info('Aborted.');

                    return self::SUCCESS;
                }
            }
        }

        $new = str_contains($current, 'KASUMI_SCRAMBLE_SALT=')
            ? preg_replace('/^KASUMI_SCRAMBLE_SALT=.*/m', 'KASUMI_SCRAMBLE_SALT=' . $salt, $current)
            : $current . PHP_EOL . 'KASUMI_SCRAMBLE_SALT=' . $salt . PHP_EOL;

        assert(is_string($new));
        file_put_contents($envPath, $new);

        $this->info('KASUMI_SCRAMBLE_SALT set successfully.');

        return self::SUCCESS;
    }
}
