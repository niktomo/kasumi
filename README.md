# Kasumi 霞

[日本語](README.ja.md) | English

Reversible 63-bit integer scrambling for Laravel — no bcmath, no GMP, no extra extensions.

```php
$scrambler->scramble(12345);          // ScrambledValue → "00000001x73riz"
$scrambler->scramble($scrambled);     // back to 12345
```

The same `scramble()` call encodes **and** decodes — no separate methods needed.

## Why

Sequential integer IDs are a liability when exposed in URLs or API responses:

- **Enumeration** — an attacker iterates `/users/1`, `/users/2`, … to harvest data or probe for IDOR vulnerabilities.
- **Business intelligence leakage** — order ID `5983` tells a competitor "this store has ~6000 orders." Two observations an hour apart reveal the transaction rate.
- **User-count estimation** — in social games and SaaS products, sequential user IDs let rivals track your growth in real time.

Kasumi scrambles IDs into opaque, fixed-length strings at the application layer, keeping your database schema and indexes untouched.

> **Note:** Scrambling alone does not replace server-side authorization checks. Always enforce access control independently.

## How it works

Based on [this algorithm](https://cs.hatenablog.jp/entry/2013/06/19/135527), applied independently to the upper 32 bits and lower 32 bits of the input:

```
scramble32(x) = inverseSalt × reverseBits32(salt × x mod 2³²) mod 2³²
```

The function is **involutory** — applying it twice returns the original value:

```
f(f(x)) = x  for all x in [0, PHP_INT_MAX]
```

- `salt × x mod 2³²` — multiplication disperses bits across the 32-bit space
- `reverseBits32(…)` — bit reversal mixes upper and lower halves
- `inverseSalt × …` — multiplication by the modular inverse makes the whole operation self-inverse

All arithmetic uses native PHP integers. No bcmath or GMP required.

## Compared to alternatives

| | **Kasumi** | jenssegers/optimus | hashids / sqids |
|---|---|---|---|
| Max input | **63-bit** (PHP_INT_MAX) | 31-bit only | 63-bit (needs bcmath/GMP) |
| Extensions | **none** | optional GMP | bcmath or GMP required |
| API | **f(f(x)) = x** | encode + decode | encode + decode |
| Output | integer or Base36 string | integer | string only |
| Laravel | **built-in** | third-party wrapper | third-party wrapper |

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

// Scramble
$result = Kasumi::scramble(12345);
echo $result;           // "00000001x73riz"  (base36, always 14 chars, salt-dependent)

// Unscramble — pass the ScrambledValue directly (no toInt() on intermediate values)
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
echo $result;           // "00000001x73riz"  (with salt 1234567891)

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

## ChecksumEncoder

`ChecksumEncoder` is a decorator that wraps any `Encoder` and adds tamper detection. It appends a 5-character base36 checksum derived from the inner encoding:

- **2-character prefix** — prepended to the output for a quick validity check
- **3 filler characters** — inserted at fixed positions inside the body as noise

Output length is `2 + innerLength + 3`. With `Base36Encoder` (14 chars), the total is **19 characters**.

`decode()` throws `\InvalidArgumentException` for any string that was not produced by this encoder — wrong length, tampered prefix, or tampered body all fail.

```php
use Kasumi\Base36Encoder;
use Kasumi\ChecksumEncoder;
use Kasumi\Scrambler;

$encoder  = new ChecksumEncoder(new Base36Encoder());
$scrambler = Scrambler::fromSalt(salt: 1234567891, encoder: $encoder);

$result = $scrambler->scramble(12345);
echo $result;           // "0b00u000i001gx73riz"  (19 chars, with salt 1234567891)

$original = $scrambler->scramble($result)->toInt(); // 12345

// Decoding a tampered string throws \InvalidArgumentException
$encoder->decode('zzzzzzzzzzzzzzzzzzzz'); // throws
```

`ChecksumEncoder` can also wrap a custom encoder:

```php
$encoder = new ChecksumEncoder(new Base62Encoder());
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

    // Encoder to use. Switch to ChecksumEncoder to add tamper detection (19-char output).
    'encoder' => \Kasumi\Base36Encoder::class,
    // 'encoder' => \Kasumi\ChecksumEncoder::class,
];
```

## Notes

- `scramble(0)` returns `0` (trivial fixed point). Avoid passing `0` if this is a concern.
- Valid input range: `[0, PHP_INT_MAX]` (63-bit non-negative integers).
- The salt must be an **odd integer**. `kasumi:salt:generate` guarantees this.
- No bcmath or GMP extension required — all arithmetic uses native PHP integers.
- The encoded string is always exactly **14 characters** (two zero-padded base36 halves) when using `Base36Encoder`, or **19 characters** when wrapped with `ChecksumEncoder`.
- To unscramble, pass the `ScrambledValue` directly to `scramble()`. Avoid calling `toInt()` on the scrambled value before passing it back, as the intermediate value may exceed `PHP_INT_MAX`.

## License

MIT
