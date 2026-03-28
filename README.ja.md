# Kasumi 霞

English | [日本語](README.ja.md)

Laravel 向け、整数の可逆スクランブルライブラリ。

`1, 2, 3` のような連番IDが `000000000009ix, 0ptyf8rz1ekw0, ...` に変換され、もう一度 scramble を呼ぶと元の値に戻ります。

```php
$scrambler->scramble(12345);          // "000000000009ix"
$scrambler->scramble(/* decode */ …); // 12345 に戻る
```

## なぜ使うのか

連番IDをそのままURLやAPIレスポンスに露出させると、総件数の推測やスクレイピングが容易になります。Kasumi はIDをスクランブルすることで、外部から推測しにくくします。

- **f(f(x)) = x** — 同じメソッド一つで暗号化・復号が完結。専用の decode は不要
- **63ビット非負整数** — [Snowflake ID](https://en.wikipedia.org/wiki/Snowflake_ID) と完全に相性が良い（Snowflake IDは63ビット非負整数）
- **決定論的** — salt が同じなら常に同じ結果。DBに保存不要
- **bcmath 不要** — すべての演算をネイティブ PHP 整数で処理

## 仕組み

[このアルゴリズム](https://cs.hatenablog.jp/entry/2013/06/19/135527) を、入力の上位32ビットと下位32ビットそれぞれに独立して適用します。

```
scramble(x) = inverseSalt × reverseBits32(salt × x mod 2³²) mod 2³²
```

この関数は **involutory（対合）** です — 2回適用すると元の値に戻ります。

```
f(f(x)) = x
```

- `salt × x` — 乗算でビットを拡散（下位ビットは偏りやすい）
- `reverseBits32(…)` — ビット反転で上位・下位を入れ替える
- `inverseSalt × …` — モジュラー逆数による乗算で往復を実現

## 要件

- PHP ^8.2
- Laravel ^12.0

## インストール

```bash
composer require niktomo/kasumi
```

salt を生成して `.env` に書き込む：

```bash
php artisan kasumi:salt:generate
```

`KASUMI_SCRAMBLE_SALT=<奇数>` が `.env` に追記されます。この値は秘密かつ不変に保ってください — **変更すると既存のスクランブル済み値がすべて無効になります**。

## 使い方

### Facade

```php
use Kasumi\Laravel\Facades\Kasumi;

$result = Kasumi::scramble(12345);

echo $result;           // "000000000009ix"  (base36、常に14文字)

// アンスクランブル — ScrambledValue をそのまま渡す（toInt() 不要）
$original = Kasumi::scramble($result)->toInt(); // 12345
```

### 依存性注入

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

### スタンドアロン（Laravel なし）

```php
use Kasumi\Scrambler;
use Kasumi\Base36Encoder;

$scrambler = Scrambler::fromSalt(
    salt: 1234567891,   // 奇数であること
    encoder: new Base36Encoder(),
);

$result = $scrambler->scramble(12345);
echo $result;           // "000000000009ix"

// アンスクランブル
$original = $scrambler->scramble($result)->toInt(); // 12345
```

## カスタム Encoder

`Kasumi\Encoder` を実装すると、文字列表現を自由に変更できます：

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

## Artisan コマンド

```bash
# salt を生成して .env に書き込む
php artisan kasumi:salt:generate

# 書き込まずに表示のみ
php artisan kasumi:salt:generate --show

# 既存の salt を上書き（警告あり）
php artisan kasumi:salt:generate --force
```

## 設定

config ファイルを公開する：

```bash
php artisan vendor:publish --tag=kasumi-config
```

`config/kasumi.php`:

```php
return [
    'scramble_salt' => env('KASUMI_SCRAMBLE_SALT'),
];
```

## 注意事項

- `scramble(0)` は `0` を返します（自明な固定点）。0 を渡す場合は考慮してください。
- 有効な入力範囲：`[1, PHP_INT_MAX]`（63ビット非負整数）
- salt は **奇数整数** でなければなりません。`kasumi:salt:generate` はこれを保証します。
- bcmath 拡張は不要です。すべての演算をネイティブ PHP 整数で処理します。
- アンスクランブルには `ScrambledValue` をそのまま `scramble()` に渡してください。中間値が `PHP_INT_MAX` を超える場合があるため、`toInt()` を経由しないでください。

## ライセンス

MIT
