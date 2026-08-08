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
     * @psalm-return void
     *
     * @return void
     */
    public function testBalanceCanBeFetched(): void
    {
        $this->requireOptIn('PURCHASER_E2E');

        $balance = $this->purchaser()->balance();

        $this->assertGreaterThanOrEqual(0, $balance);
    }

    /**
     * 冪等であること（2回呼んでも積み増さないこと）まで確認する。
     *
     * @psalm-return void
     *
     * @return void
     */
    public function testEnsureBalanceIsIdempotent(): void
    {
        $this->requireOptIn('PURCHASER_E2E_DEPOSIT');

        $purchaser = $this->purchaser();

        $first = $purchaser->ensureBalance(PurchaserCore::DEFAULT_TARGET_BALANCE);
        $this->assertGreaterThanOrEqual(PurchaserCore::DEFAULT_TARGET_BALANCE, $first);

        $second = $purchaser->ensureBalance(PurchaserCore::DEFAULT_TARGET_BALANCE);
        $this->assertSame($first, $second, '2回目の ensureBalance() で残高が増えている（入金が冪等でない）');
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testPurchase(): void
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
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
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
