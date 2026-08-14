<?php

declare(strict_types=1);

namespace BVP\Purchaser\Tests;

use BVP\Purchaser\PurchaserCore;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * 実際にテレボートへ接続する結合テスト。
 *
 * 段階ごとに別の環境変数でオプトインする。既定では全てスキップされる。
 *   PURCHASER_E2E=1          ログインと残高照会のみ（お金は動かない）
 *   PURCHASER_E2E_DEPOSIT=1  入金まで行う（お金が動く）
 *   PURCHASER_E2E_PURCHASE=1 実際に舟券を買う（お金が減る）
 *
 * @author shimomo
 */
final class PurchaserCoreTest extends PHPUnitTestCase
{
    use TeleboatCredentials;

    /**
     * ログイン・投票ウィンドウの生成・お知らせダイアログ・残高照会の DOM を、
     * 一切お金を動かさずに通す。サイト改修の検知に使える。
     *
     * @return void
     */
    public function test_balance_can_be_fetched(): void
    {
        $this->requireOptIn('PURCHASER_E2E');

        $balance = $this->purchaser()->balance();

        $this->assertGreaterThanOrEqual(0, $balance);
    }

    /**
     * 冪等であること（2回呼んでも積み増さないこと）まで確認する。
     *
     * @return void
     */
    public function test_ensure_balance_is_idempotent(): void
    {
        $this->requireOptIn('PURCHASER_E2E_DEPOSIT');

        $purchaser = $this->purchaser();

        $first = $purchaser->ensureBalance(PurchaserCore::DEFAULT_TARGET_BALANCE);
        $this->assertGreaterThanOrEqual(PurchaserCore::DEFAULT_TARGET_BALANCE, $first);

        $second = $purchaser->ensureBalance(PurchaserCore::DEFAULT_TARGET_BALANCE);
        $this->assertSame($first, $second, '2回目の ensureBalance() で残高が増えている（入金が冪等でない）');
    }
    /**
     * @return void
     */

    public function test_purchase(): void
    {
        $this->requireOptIn('PURCHASER_E2E_PURCHASE');

        $receipt = $this->purchaser()->purchase(stadiumNumber: 24, number: 12, type: 6, focuses: [
            '1-2-3' => 100,
            '1-2-4' => 100,
        ]);

        $this->assertSame(200, $receipt->totalAmount);
        $this->assertSame(24, $receipt->stadiumNumber);
        $this->assertSame(12, $receipt->raceNumber);
    }

    /**
     * 1回のログインで2レース処理する。
     *
     * 完了画面から場選択画面へ戻る手順（return-to-race-select）は、この経路でしか
     * 通らない。レース番号は実行する日の発売状況に合わせて書き換えること。
     *
     * @return void
     */
    public function test_purchase_many(): void
    {
        $this->requireOptIn('PURCHASER_E2E_PURCHASE');

        $batch = $this->purchaser()
            ->setMaxBatchAmount(1000)
            ->purchaseMany([
                ['stadiumNumber' => 24, 'number' => 11, 'type' => 6, 'focuses' => ['1-2-3' => 100]],
                ['stadiumNumber' => 24, 'number' => 12, 'type' => 6, 'focuses' => ['1-2-3' => 100]],
            ]);

        $this->assertFalse(
            $batch->hasFailure(),
            '途中で中止している: ' . (string) json_encode($batch->failure, JSON_UNESCAPED_UNICODE)
        );

        $this->assertSame([], $batch->skipped);
        $this->assertSame(2, $batch->count());
        $this->assertSame(200, $batch->totalAmount);
        $this->assertSame(11, $batch->receipts[0]->raceNumber);
        $this->assertSame(12, $batch->receipts[1]->raceNumber);

        // 2レース目だけが通る画面遷移。ここが欠けていれば1レース目を撮り直しているだけ。
        $this->assertArrayNotHasKey('return-to-race-select', $batch->receipts[0]->stepMilliseconds);
        $this->assertArrayHasKey('return-to-race-select', $batch->receipts[1]->stepMilliseconds);

        // ログインは1回で済んでいるので、2レース目に login の計測が乗っていても
        // それはセッション単位の値（レースごとに再ログインしていない）。
        $this->assertSame(
            $batch->stepMilliseconds['login'] ?? null,
            $batch->receipts[1]->stepMilliseconds['login'] ?? null
        );
    }
    /**
     * @return \BVP\Purchaser\PurchaserCore
     */

    private function purchaser(): PurchaserCore
    {
        // コンストラクタではブラウザを起動しないので、スキップされるテストでも無害。
        return (new PurchaserCore())
            ->setSubscriberNumber($this->credential('SUBSCRIBER_NUMBER'))
            ->setPersonalIdentificationNumber($this->credential('PERSONAL_IDENTIFICATION_NUMBER'))
            ->setAuthenticationPassword($this->credential('AUTHENTICATION_PASSWORD'))
            ->setPurchasePassword($this->credential('PURCHASE_PASSWORD'))
            ->setArtifactDirectory(sys_get_temp_dir() . '/bvp-purchaser-artifacts');
    }
}
