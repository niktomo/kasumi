# Kasumi 霞

[日本語](README.ja.md) | English

Reversible integer scrambling for Laravel.

Sequential IDs like `1, 2, 3` become `000000000009ix, 0ptyf8rz1ekw0, ...` — and calling scramble again returns the original value.

```php
$scrambler->scramble(12345);          // "000000000009ix"
$scrambler->scramble(/* decode */ …); // back to 12345
```

## How it works

Based on [this algorithm](https://cs.hatenablog.jp/entry/2013/06/19/135527), applied independently to the upper 32 bits and lower 32 bits of the input:

```
scramble(x) = inverseSalt × reverseBits32(salt × x mod 2³²) mod 2³²
```

The function is **involutory** — applying it twice returns the original value:

```
f(f(x)) = x
```

- `salt × x` — multiplication spreads bits (lower bits tend to be stable)
- `reverseBits32(…)` — bit reversal swaps lower and upper bits
- `inverseSalt × …` — multiplication by the modular inverse restores the round-trip

All arithmetic uses native PHP integers (no bcmath required).

## Requirements

- PHP ^8.2
- Laravel ^12.0

## Installation

```bash
composer require niktomo/kasumi
```

Generate a salt and write it to `.env`:

```bash
php artisan kasumi:salt:generate
```

This adds `KASUMI_SCRAMBLE_SALT=<odd integer>` to your `.env`. Keep this value secret and stable — **changing it invalidates all existing scrambled values**.

## Usage

### Facade

```php
use Kasumi\Laravel\Facades\Kasumi;

$result = Kasumi::scramble(12345);

echo $result;           // "000000000009ix"  (base36, always 14 chars)

// Unscramble — pass the ScrambledValue directly (no toInt() needed)
$original = Kasumi::scramble($result)->toInt(); // 12345
```

### Dependency Injection

```php
use Kasumi\Scrambler;

class UserController
{
    public function __construct(private Scrambler $scrambler) {}

    public function show(string $encoded): Response
    {
        $encoder         = new \Kasumi\Base36Encoder();
        [$upper, $lower] = $encoder->decode($encoded);
        $scrambled       = new \Kasumi\ScrambledValue($upper, $lower, $encoder);
        $id              = $this->scrambler->scramble($scrambled)->toInt();

        $user = User::findOrFail($id);
        // …
    }
}
```

### Standalone (without Laravel)

```php
use Kasumi\Scrambler;
use Kasumi\Base36Encoder;

$scrambler = Scrambler::fromSalt(
    salt: 1234567891,   // must be odd
    encoder: new Base36Encoder(),
);

$result = $scrambler->scramble(12345);
echo $result;           // "000000000009ix"

// Unscramble
$original = $scrambler->scramble($result)->toInt(); // 12345
```

## Custom Encoder

Implement `Kasumi\Encoder` to use a different string representation:

```php
use Kasumi\Encoder;

class Base62Encoder implements Encoder
{
    public function encode(int $upper, int $lower): string { /* … */ }
    /** @return array{0: int, 1: int} */
    public function decode(string $s): array { /* … */ }
}

$scrambler = Scrambler::fromSalt($salt, new Base62Encoder());
```

## Artisan Commands

```bash
# Generate a new salt and write to .env
php artisan kasumi:salt:generate

# Display without writing
php artisan kasumi:salt:generate --show

# Overwrite existing salt (with warning)
php artisan kasumi:salt:generate --force
```

## Config

Publish the config file:

```bash
php artisan vendor:publish --tag=kasumi-config
```

`config/kasumi.php`:

```php
return [
    'scramble_salt' => env('KASUMI_SCRAMBLE_SALT'),
];
```

## Notes

- `scramble(0)` returns `0` (trivial fixed point). Avoid passing `0` if this is a concern.
- Valid input range: `[1, PHP_INT_MAX]` (63-bit non-negative integers).
- The salt must be an **odd integer**. `kasumi:salt:generate` guarantees this.
- No bcmath extension required — all arithmetic uses native PHP integers.
- To unscramble, pass the `ScrambledValue` directly to `scramble()`. Avoid calling `toInt()` on the scrambled value before passing it back, as the intermediate value may exceed `PHP_INT_MAX`.

## License

MIT
