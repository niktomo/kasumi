# Kasumi 霞

English | [日本語](README.ja.md)

Laravel 向け、63ビット整数の可逆スクランブルライブラリ。

`1, 2, 3` のような連番IDが `00000000009ix, 0ptyf8rz1ekw, ...` に変換され、もう一度 scramble を呼ぶと元の値に戻ります。

```php
$scrambler->scramble(12345);          // "00000000009ix"
$scrambler->scramble(/* decode */ …); // 12345 に戻る
```

## なぜ使うのか

連番IDをそのままURLやAPIレスポンスに露出させると、総件数の推測やスクレイピングが容易になります。Kasumi はIDをスクランブルすることで、外部から推測しにくくします。

- **f(f(x)) = x** — 同じメソッド一つで暗号化・復号が完結。専用の decode は不要
- **63ビット非負整数** — [Snowflake ID](https://en.wikipedia.org/wiki/Snowflake_ID) と完全に相性が良い（Snowflake IDは63ビット非負整数）
- **決定論的** — salt が同じなら常に同じ結果。DBに保存不要

## 仕組み

[このアルゴリズム](https://cs.hatenablog.jp/entry/2013/06/19/135527) をベースにしています。

```
scramble(x) = inverseSalt × reverseBits63(salt × x mod 2⁶³) mod 2⁶³
```

この関数は **involutory（対合）** です — 2回適用すると元の値に戻ります。

```
f(f(x)) = x
```

- `salt × x` — 乗算でビットを拡散（下位ビットは偏りやすい）
- `reverseBits63(…)` — ビット反転で上位・下位を入れ替える
- `inverseSalt × …` — モジュラー逆数による乗算で往復を実現

## 要件

- PHP ^8.4
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

echo $result;           // "00000000009ix"  (base36、常に13文字)
echo $result->toInt();  // スクランブル後の整数値

// アンスクランブル — 同じメソッドを使う
$original = Kasumi::scramble($result->toInt())->toInt(); // 12345
```

### 依存性注入

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

### スタンドアロン（Laravel なし）

```php
use Kasumi\Scrambler;
use Kasumi\Base36Encoder;

$scrambler = Scrambler::fromSalt(
    salt: 1234567891,   // 奇数であること
    encoder: new Base36Encoder(),
);

$result = $scrambler->scramble(12345);
echo $result;           // "00000000009ix"
echo $result->toInt();  // スクランブル後の整数値
```

## カスタム Encoder

`Kasumi\Encoder` を実装すると、文字列表現を自由に変更できます：

```php
use Kasumi\Encoder;

class Base62Encoder implements Encoder
{
    public function encode(int $n): string { /* … */ }
    public function decode(string $s): int { /* … */ }
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
- bcmath 拡張が必要です。PHP 8.4+ には同梱されています。

## ライセンス

MIT
