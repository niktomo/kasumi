# Kasumi 霞

English | [日本語](README.ja.md)

Laravel 向け、63ビット整数の可逆スクランブルライブラリ。bcmath・GMP 不要。

```php
$scrambler->scramble(12345);      // ScrambledValue → "00000001x73riz"
$scrambler->scramble($scrambled); // 12345 に戻る
```

エンコードも復号も同じ `scramble()` 一つで完結。専用の decode メソッドは不要です。

## なぜ使うのか

連番 ID をそのまま URL や API レスポンスに露出させると、次のリスクが生じます。

- **列挙攻撃** — `/users/1`, `/users/2`, … と連番を試すだけで、他ユーザーのデータへのアクセスや IDOR 脆弱性の探索が可能になります。
- **ビジネスインテリジェンスの漏洩** — 注文ID `5983` が見えれば「このストアには約6000件の注文がある」とわかります。1時間おきに2回観測するだけで、競合他社に注文数・成長率を知られます。
- **ユーザー数の推定** — ソーシャルゲームや SaaS では、連番ユーザー ID から登録者数の増加がリアルタイムで計測されます。

Kasumi はアプリケーション層で ID をスクランブルし、不透明な固定長文字列に変換します。データベースのスキーマやインデックスはそのままです。

> **注意：** スクランブルはサーバーサイドの認可チェックを代替するものではありません。アクセス制御は必ず別途実装してください。

## 仕組み

[このアルゴリズム](https://cs.hatenablog.jp/entry/2013/06/19/135527) を、入力の上位32ビットと下位32ビットそれぞれに独立して適用します。

```
scramble32(x) = inverseSalt × reverseBits32(salt × x mod 2³²) mod 2³²
```

この関数は **involutory（対合）** です — 2回適用すると元の値に戻ります。

```
f(f(x)) = x  （x が [0, PHP_INT_MAX] の全ての値について）
```

- `salt × x mod 2³²` — 乗算でビットを32ビット空間全体に拡散
- `reverseBits32(…)` — ビット反転で上位・下位ビットを入れ替え
- `inverseSalt × …` — モジュラー逆数による乗算で対合性を実現

すべての演算をネイティブ PHP 整数で処理します。bcmath も GMP も不要。

## 類似ライブラリとの比較

| | **Kasumi** | jenssegers/optimus | hashids / sqids |
|---|---|---|---|
| 最大入力 | **63ビット**（PHP_INT_MAX） | 31ビット上限 | 63ビット（bcmath/GMP が必要） |
| 拡張モジュール | **不要** | 32ビット環境で GMP 推奨 | bcmath または GMP が必須 |
| API | **f(f(x)) = x** | encode + decode | encode + decode |
| 出力形式 | 整数 or Base36 文字列 | 整数のみ | 文字列のみ |
| Laravel 統合 | **公式同梱** | サードパーティ | サードパーティ |

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

// スクランブル
$result = Kasumi::scramble(12345);
echo $result;           // "00000001x73riz"  (base36、常に14文字、salt に依存)

// アンスクランブル — ScrambledValue をそのまま渡す（中間値で toInt() しない）
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
echo $result;           // "00000001x73riz"  (salt=1234567891 の場合)

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

## ChecksumEncoder

`ChecksumEncoder` は任意の `Encoder` をラップし、改ざん検出を追加するデコレータです。内部エンコード結果から 5 文字の base36 チェックサムを生成し、次のように付加します：

- **2 文字プレフィックス** — 出力の先頭に付加する簡易チェック用
- **3 文字フィラー** — ノイズとしてボディ内の固定位置に挿入

出力長は `2 + innerLength + 3`。`Base36Encoder`（14 文字）を内部に使う場合、合計 **19 文字** になります。

`decode()` はこのエンコーダが生成していない文字列（長さ不正・プレフィックス改ざん・ボディ改ざん）に対して `\InvalidArgumentException` をスローします。

```php
use Kasumi\Base36Encoder;
use Kasumi\ChecksumEncoder;
use Kasumi\Scrambler;

$encoder   = new ChecksumEncoder(new Base36Encoder());
$scrambler = Scrambler::fromSalt(salt: 1234567891, encoder: $encoder);

$result = $scrambler->scramble(12345);
echo $result;           // "0b00u000i001gx73riz"  (19文字、salt=1234567891 の場合)

$original = $scrambler->scramble($result)->toInt(); // 12345

// 改ざんされた文字列は \InvalidArgumentException をスロー
$encoder->decode('zzzzzzzzzzzzzzzzzzzz'); // throws
```

カスタム Encoder をラップすることも可能です：

```php
$encoder = new ChecksumEncoder(new Base62Encoder());
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

    // 使用する Encoder。ChecksumEncoder に切り替えると改ざん検出が有効になります（出力19文字）。
    'encoder' => \Kasumi\Base36Encoder::class,
    // 'encoder' => \Kasumi\ChecksumEncoder::class,
];
```

## 注意事項

- `scramble(0)` は `0` を返します（自明な固定点）。0 を渡す場合は考慮してください。
- 有効な入力範囲：`[0, PHP_INT_MAX]`（63ビット非負整数）
- salt は **奇数整数** でなければなりません。`kasumi:salt:generate` はこれを保証します。
- bcmath・GMP 拡張は不要です。すべての演算をネイティブ PHP 整数で処理します。
- エンコード後の文字列は `Base36Encoder` 使用時は常に **14文字固定**（ゼロパディングされた Base36 を2分割して連結）。`ChecksumEncoder` でラップした場合は **19文字**。
- アンスクランブルには `ScrambledValue` をそのまま `scramble()` に渡してください。中間値が `PHP_INT_MAX` を超える場合があるため、`toInt()` を経由しないでください。

## ライセンス

MIT
