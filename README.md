# Kasumi 霞

[日本語](README.ja.md) | English

Reversible 63-bit integer scrambling for Laravel.

Sequential IDs like `1, 2, 3` become `00000000009ix, 0ptyf8rz1ekw, ...` — and calling scramble again returns the original value.

```php
$scrambler->scramble(12345);          // "00000000009ix"
$scrambler->scramble(/* decode */ …); // back to 12345
```

## How it works

Based on [this algorithm](https://cs.hatenablog.jp/entry/2013/06/19/135527):

```
scramble(x) = inverseSalt × reverseBits63(salt × x mod 2⁶³) mod 2⁶³
```

The function is **involutory** — applying it twice returns the original value:

```
f(f(x)) = x
```

- `salt × x` — multiplication spreads bits (lower bits tend to be stable)
- `reverseBits63(…)` — bit reversal swaps lower and upper bits
- `inverseSalt × …` — multiplication by the modular inverse restores the round-trip

## Requirements

- PHP ^8.2
- Laravel ^12.0
- bcmath extension (bundled with PHP 8.4+; install separately on PHP 8.2/8.3)

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

echo $result;           // "00000000009ix"  (base36, always 13 chars)
echo $result->toInt();  // scrambled integer

// Unscramble — same method
$original = Kasumi::scramble($result->toInt())->toInt(); // 12345
```

### Dependency Injection

```php
use Kasumi\Scrambler;

class UserController
{
    public function __construct(private Scrambler $scrambler) {}

    public function show(string $encoded): Response
    {
        $id   = $this->scrambler->scramble(
            (new \Kasumi\Base36Encoder())->decode($encoded)
        )->toInt();

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
echo $result;           // "00000000009ix"
echo $result->toInt();  // scrambled int
```

## Custom Encoder

Implement `Kasumi\Encoder` to use a different string representation:

```php
use Kasumi\Encoder;

class Base62Encoder implements Encoder
{
    public function encode(int $n): string { /* … */ }
    public function decode(string $s): int { /* … */ }
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
- bcmath extension is required. It is bundled with PHP 8.4+. Install separately on PHP 8.2/8.3.

## License

MIT
