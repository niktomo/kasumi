<?php

declare(strict_types=1);

namespace Kasumi\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Kasumi\ScrambledValue;
use Kasumi\Scrambler;

/**
 * @method static ScrambledValue scramble(int $n)
 *
 * @see Scrambler
 */
class Kasumi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Scrambler::class;
    }
}
