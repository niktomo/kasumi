<?php

declare(strict_types=1);

namespace Kasumi;

/**
 * 2^63 as a bcmath-compatible string.
 * PHP_INT_MAX = 2^63 - 1, so this value cannot be expressed as int.
 */
final class Modulus
{
    public const string VALUE = '9223372036854775808';
}
