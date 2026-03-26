<?php

declare(strict_types=1);

namespace Kasumi\Laravel;

use Illuminate\Support\ServiceProvider;
use Kasumi\Base36Encoder;
use Kasumi\Encoder;
use Kasumi\Laravel\Console\SaltGenerateCommand;
use Kasumi\Scrambler;

class KasumiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/kasumi.php', 'kasumi');

        $this->app->singleton(Scrambler::class, function () {
            /** @var array{scramble_salt: int} $config */
            $config = config('kasumi');

            if (empty($config['scramble_salt'])) {
                throw new \RuntimeException(
                    'KASUMI_SCRAMBLE_SALT is not set. Run: php artisan kasumi:salt:generate'
                );
            }

            return Scrambler::fromSalt($config['scramble_salt'], new Base36Encoder());
        });

        $this->app->bind(Encoder::class, Base36Encoder::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/kasumi.php' => config_path('kasumi.php'),
        ], 'kasumi-config');

        if ($this->app->runningInConsole()) {
            $this->commands([SaltGenerateCommand::class]);
        }
    }
}
