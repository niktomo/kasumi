# CLAUDE.md

## Project Overview

**Kasumi**（霞） — 63ビット整数を可逆スクランブルするPHPライブラリ。
モジュラー逆数（乗算逆元）＋ビット反転を用いた involution: `scramble(scramble(x)) = x`

## Commands

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=512M
vendor/bin/pint
```

## Architecture

- `src/ScrambleKey` — salt + inverseSalt の値オブジェクト（イミュータブル）
- `src/ScrambleKeyFactory` — 奇数 salt から Newton–Raphson lifting で逆数を計算し ScrambleKey を生成
- `src/Scrambler` — メインクラス。`fromSalt()` で config から生成。`scramble()` は involution
- `src/ScrambledValue` — scramble 結果の値オブジェクト。Stringable 実装、Encoder に委譲
- `src/Encoder` — encode/decode インターフェース
- `src/Base36Encoder` — 0-9a-z の13文字固定幅実装

