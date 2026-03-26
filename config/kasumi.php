<?php

return [
    /*
     * An odd integer used as the scramble salt.
     * Keep this value secret and stable — changing it invalidates all existing scrambled values.
     *
     * Generate a key: php -r "echo (random_int(1, PHP_INT_MAX) | 1);"
     */
    'scramble_salt' => (int) env('KASUMI_SCRAMBLE_SALT'),
];
