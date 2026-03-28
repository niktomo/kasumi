# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-03-28

### Added
- `Scrambler` — involutory 63-bit integer scrambler (`f(f(x)) = x`) using modular multiplicative inverse + bit reversal. No bcmath or GMP required.
- `Base36Encoder` — encodes scrambled values as 14-character zero-padded base36 strings (0–9, a–z).
- `ChecksumEncoder` — decorator that wraps any `Encoder` and adds tamper detection via a 5-character base36 checksum (2-char prefix + 3 filler chars inserted at fixed positions). Output is 19 characters when wrapping `Base36Encoder`.
- `ScrambledValue` — value object representing a scrambled result; implements `Stringable` and provides `toInt()`.
- Laravel service provider with auto-discovery, `Kasumi` facade, and `kasumi.encoder` config key to switch between `Base36Encoder` and `ChecksumEncoder`.
- `php artisan kasumi:salt:generate` — generates an odd integer salt and writes it to `.env`. Supports `--show` and `--force` options.
- PHPStan level 8 compliance across all source and test files.
- GitHub Actions CI matrix across PHP 8.2, 8.3, and 8.4.
