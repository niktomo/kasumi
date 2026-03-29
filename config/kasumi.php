<?php

use Kasumi\Base36Encoder;

return [
    /*
     * An odd integer used as the scramble salt.
     * Keep this value secret and stable — changing it invalidates all existing scrambled values.
     *
     * Generate a key: php artisan kasumi:salt:generate
     */
    'scramble_salt' => (int) env('KASUMI_SCRAMBLE_SALT'),

    /*
     * Encoder class to use for string representation of scrambled values.
     *
     * Built-in options:
     *   \Kasumi\Base36Encoder::class    — 14-character base36 string (default)
     *   \Kasumi\ChecksumEncoder::class  — wraps Base36Encoder; adds tamper detection (19 chars)
     *
     * You may also specify any class that implements \Kasumi\Encoder.
     */
    'encoder' => Base36Encoder::class,
];
