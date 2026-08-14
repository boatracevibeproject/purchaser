# Purchaser for Boatrace Venture Project

[![php](https://poser.pugx.org/bvp/purchaser/require/php)](https://packagist.org/packages/bvp/purchaser)
[![stable](https://poser.pugx.org/bvp/purchaser/v/stable)](https://packagist.org/packages/bvp/purchaser)
[![license](https://poser.pugx.org/bvp/purchaser/license)](https://packagist.org/packages/bvp/purchaser)

[![test](https://github.com/boatracevibeproject/purchaser/actions/workflows/test.yml/badge.svg)](https://github.com/boatracevibeproject/purchaser/actions/workflows/test.yml)
[![lint](https://github.com/boatracevibeproject/purchaser/actions/workflows/lint.yml/badge.svg)](https://github.com/boatracevibeproject/purchaser/actions/workflows/lint.yml)
[![psalm](https://github.com/boatracevibeproject/purchaser/actions/workflows/psalm.yml/badge.svg)](https://github.com/boatracevibeproject/purchaser/actions/workflows/psalm.yml)
[![security](https://github.com/boatracevibeproject/purchaser/actions/workflows/security.yml/badge.svg)](https://github.com/boatracevibeproject/purchaser/actions/workflows/security.yml)
[![keepalive](https://github.com/boatracevibeproject/purchaser/actions/workflows/keepalive.yml/badge.svg)](https://github.com/boatracevibeproject/purchaser/actions/workflows/keepalive.yml)

Purchaser は、舟券を自動購入するための PHP ライブラリです。

## インストール

```bash
composer require bvp/purchaser
```

## 使い方

### 朝に入金だけ済ませておく

`ensureBalance()` は投票せず、残高を指定額まで満たすだけのメソッドです。
「入金する」ではなく「この額まで満たす」形なので、**何度呼んでも結果が同じ**になります
（catch-up で二重に走っても積み増しません）。

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use BVP\Purchaser\Purchaser;

$balance = Purchaser::setSubscriberNumber('xxxxxxxx')      // 加入者番号
    ->setPersonalIdentificationNumber('xxxx')              // 暗証番号
    ->setAuthenticationPassword('xxxxxx')                  // 認証用パスワード
    ->setPurchasePassword('xxxxxx')                        // 投票用パスワード
    ->setArtifactDirectory(__DIR__ . '/storage/purchaser') // 失敗時の画面を保存する
    ->ensureBalance(); // 既定 10,000 円まで満たす
```

内部の手順は次の4段で、入金エラーを発生させないために**最後の再照会まで必ず通します**。

1. 必要額を確定する
2. 残高照会
3. 不足していれば、不足分を 1,000 円単位に切り上げて入金
4. 残高照会で必要額を満たしたことを確認（入金の反映は非同期）

残高が既に足りていれば手順3を飛ばします。このとき手順2の照会が手順4の確認を兼ねます。

締切直前の投票からこの往復を外せるので、**朝に1回呼んでおくのが基本の運用**です。
あわせて、ログイン・投票ウィンドウの生成・お知らせダイアログ・残高照会の DOM を
お金を動かさずに通るので、サイト改修の**日次カナリア**にもなります。

### 舟券を買う

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use BVP\Purchaser\Purchaser;

// type: 単勝 => 1, 複勝 => 2, 2連単 => 3, 2連複 => 4, 拡連複 => 5, 3連単 => 6, 3連複 => 7
$receipt = Purchaser::setSubscriberNumber('xxxxxxxx')
    ->setPersonalIdentificationNumber('xxxx')
    ->setAuthenticationPassword('xxxxxx')
    ->setPurchasePassword('xxxxxx')
    ->setArtifactDirectory(__DIR__ . '/storage/purchaser')
    ->setMaxTotalAmount(10000)                             // 1レースの購入金額合計の上限
    ->setDeadline(new DateTimeImmutable('2026-08-08 15:32:00')) // 締切時刻
    ->purchase(stadiumNumber: 24, number: 12, type: 6, focuses: [
        '1-2-3' => 100, // 組番 => 購入金額
        '1-2-4' => 100,
        '1-3-2' => 100,
        '1-3-4' => 100,
    ]);

echo $receipt->totalAmount;         // 400
echo $receipt->elapsedMilliseconds; // 所要時間（締切何分前まで詰められるかの実測に使う）
print_r($receipt->stepMilliseconds);
```

`purchase()` は投票の前に `ensureBalance()` を通すので、残高が足りなければその場でも入金します。
朝に満たしてあれば照会1回で素通りします。

戻り値の `Receipt` に何をいくら買ったかが入るので、そのまま購入履歴に残せます。

### 複数レースをまとめて買う

`purchaseMany()` は、**1回のログインで複数レース**を処理します。
ログイン・投票ウィンドウの生成・残高の確保はセッションに1回で済み、
2レース目以降は投票完了画面から場選択画面へ戻って同じ手順を繰り返します。

```php
$batch = Purchaser::setSubscriberNumber('xxxxxxxx')
    ->setPersonalIdentificationNumber('xxxx')
    ->setAuthenticationPassword('xxxxxx')
    ->setPurchasePassword('xxxxxx')
    ->setArtifactDirectory(__DIR__ . '/storage/purchaser')
    ->setMaxTotalAmount(10000) // 1レースの上限
    ->setMaxBatchAmount(30000) // 1セッション合計の上限
    ->purchaseMany([
        [
            'stadiumNumber' => 24,
            'number' => 11,
            'type' => 6,
            'focuses' => ['1-2-3' => 100, '1-2-4' => 100],
            'deadline' => new DateTimeImmutable('2026-08-08 15:02:00'),
        ],
        [
            'stadiumNumber' => 24,
            'number' => 12,
            'type' => 6,
            'focuses' => ['1-3-2' => 100],
            'deadline' => new DateTimeImmutable('2026-08-08 15:32:00'),
        ],
    ]);

foreach ($batch->receipts as $receipt) {
    echo $receipt->raceNumber;     // 11, 12
    echo $receipt->receiptNumber;  // 契約番号
}

echo $batch->totalAmount; // 300
```

レースは**締切の早い順**に並べます。逆順になっていると、ブラウザを起動する前に例外にします
（黙って並べ替えると呼び出し側の意図と食い違うため）。
`'deadline'` を省いたレースには `setDeadline()` の値が使われます。

残高は**全レースの合計で1回だけ**満たします。レースの合間に入金と反映待ち（最大60秒）が
入ると次の締切に間に合わないので、入金はセッションの先頭に置き切ります。
既定の入金上限は 10,000 円なので、合計がそれを超えるなら `setMaxDepositAmount()` も上げてください。

#### 途中で失敗したとき

`purchaseMany()` は、**投票できたレースの控えを必ず返します**。例外にすると、既に動いた
お金の記録が残らないからです。中止したかどうかは戻り値で見てください。

```php
if ($batch->hasFailure()) {
    // ここまでの $batch->receipts は成立している
    error_log($batch->failure['reason']);
}

foreach ($batch->skipped as $skipped) {
    error_log("{$skipped['raceNumber']}R: {$skipped['reason']}");
}
```

| 起きたこと | 扱い |
|---|---|
| 買い目・レース番号・金額が不正 | **全レース中止**。ブラウザを起動する前に例外 |
| 締切をまたいだ | そのレースだけ飛ばして次へ（`skipped`） |
| 残高が足りない | そのレースだけ飛ばして次へ（`skipped`） |
| 画面操作に失敗した | 以降のレースを中止（`failure`）。盤面が読めない状態を次に持ち越さない |
| 購入成立金額が投票額と合わない | 以降のレースを中止（`failure`） |
| 1レースも投票できなかった | 例外。単発の `purchase()` と同じ |

最後の「購入成立金額」は完了画面の値です。締切済などで一部のベットが不成立でも
完了画面には着くので、金額を突き合わせて気づけるようにしてあります
（`Receipt::$acceptedAmount`。画面から読めなければ `null` になり、判定はしません）。

なお `purchaseMany()` は**同じ節のレースをまとめて処理する**ことを想定しています。
1レース目と最終レースが1時間以上離れるような使い方では、テレボート側のセッション切れと
ロックの占有時間が問題になるので、その単位で呼び分けてください。

### 安全弁

| メソッド | 既定 | 役割 |
|---|---|---|
| `setMaxTotalAmount()` | 10,000 円 | 1レースの購入金額合計の上限。呼び出し側のバグを止める |
| `setMaxBatchAmount()` | `setMaxTotalAmount()` の値 | 1セッションの購入金額合計の上限。N レースで N 倍まで通るのを止める |
| `setMaxDepositAmount()` | 10,000 円 | 1回の入金指示の上限。想定外の巨額入金を止める |
| `setDeadline()` | なし | 締切間際になったら**投票せずに**中止する |
| `setArtifactDirectory()` | なし | 失敗時にスクリーンショットと HTML を保存する |
| `setLockPath()` | `/tmp/bvp-purchaser.lock` | 朝の入金・締切前の投票・手動ログインを排他する |

買い目・金額の検証（組番の要素数、艇番の範囲、100 円単位、重複）は
**ブラウザを起動する前**に済ませるので、直せない入力はテレボートに触る前に落ちます。

## クイックスタート

### Step 1

このリポジトリをクローンします。

```bash
git clone git@github.com:boatracevibeproject/purchaser.git
```

### Step 2

必要なライブラリをインストールします。

```bash
cd purchaser && composer install
```

### Step 3

加入者番号、暗証番号、認証用パスワード、投票用パスワード、買い目をそれぞれ書き換えます。

```bash
code example.php
```

### Step 4

Google Chrome の [Selenium Grid Server](https://github.com/SeleniumHQ/docker-selenium) を起動します。

```bash
docker run -d -p 4444:4444 --shm-size="2g" --name selenium-standalone-chrome selenium/standalone-chrome:4.2.2-20220622
```

### Step 5

購入プログラムを実行します。

```bash
php example.php
```

## テスト

買い目の解釈・入金額の計算・ロックは Selenium も会員情報も無しで実行できます。

```bash
vendor/bin/phpunit
```

テレボートへ実際に接続する結合テストは、段階ごとに別の環境変数でオプトインします
（既定では全てスキップされます）。

| 環境変数 | 範囲 |
|---|---|
| `PURCHASER_E2E=1` | ログインと残高照会のみ（お金は動かない） |
| `PURCHASER_E2E_DEPOSIT=1` | 入金まで行う（お金が動く） |
| `PURCHASER_E2E_PURCHASE=1` | 実際に舟券を買う（お金が減る） |

会員情報を環境変数に設定します。

```bash
$env:SUBSCRIBER_NUMBER = "加入者番号"
$env:PERSONAL_IDENTIFICATION_NUMBER = "暗証番号"
$env:AUTHENTICATION_PASSWORD = "認証用パスワード"
$env:PURCHASE_PASSWORD = "投票用パスワード"
```

Selenium Server を起動します。

```bash
npm install selenium-standalone --save-dev
npx selenium-standalone install
npx selenium-standalone start
```

## 免責事項

このライブラリを使用して舟券を自動購入する際には、以下の点に十分ご注意ください。

1. 自己責任

本ライブラリを使用した結果として発生した如何なる損害（経済的損失、データ損失、法的問題など）についても、作者および貢献者は一切の責任を負いません。すべてのリスクは利用者自身が負うものとします。

2. 法的遵守

舟券の購入や自動化に関する法規制は国や地域によって異なる場合があります。本ライブラリを使用する前に、必ずご自身の居住地域における関連法規を確認してください。不適切または違法な使用について、作者は一切の責任を負いません。

3. 利用上のリスク

公営競技の投票システムやAPI仕様の変更、技術的トラブル、予期せぬ動作による購入エラーが発生する可能性があります。これらによって生じた損失について、作者は責任を負いません。

### 推奨事項

- 本ライブラリをテスト環境や小額で検証してから使用してください。
- ソースコードを理解した上で、責任を持って運用してください。
- ご不明点がある場合は、必ず専門家に相談してください。

## ライセンス

Purchaser は [MIT license](LICENSE) の元で公開されています。
