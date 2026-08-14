<?php

declare(strict_types=1);

namespace BVP\Purchaser\Tests;

use BVP\Purchaser\Purchaser;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * 「不足分だけ1,000円単位に切り上げて入金する」の計算部分。
 *
 * @author shimomo
 */
final class DepositAmountTest extends PHPUnitTestCase
{
    /**
     * @return void
     */
    public function test_no_deposit_when_balance_is_sufficient(): void
    {
        $this->assertSame(0, Purchaser::requiredDepositAmount(10000, 10000));
        $this->assertSame(0, Purchaser::requiredDepositAmount(12000, 10000));
    }

    /**
     * @return void
     */
    public function test_shortfall_is_rounded_up_to_thousand_yen(): void
    {
        // 不足 10,000 円 → ちょうど 10,000 円
        $this->assertSame(10000, Purchaser::requiredDepositAmount(0, 10000));

        // 不足 1 円でも 1,000 円入金しないと単位に乗らない
        $this->assertSame(1000, Purchaser::requiredDepositAmount(9999, 10000));

        // 不足 2,300 円 → 3,000 円
        $this->assertSame(3000, Purchaser::requiredDepositAmount(7700, 10000));

        // 不足がちょうど単位なら切り上げない
        $this->assertSame(2000, Purchaser::requiredDepositAmount(8000, 10000));
    }

    /**
     * 入金後の残高が必要額を必ず満たすこと（切り上げの向きが逆でないこと）。
     *
     * @return void
     */
    public function test_resulting_balance_always_reaches_required(): void
    {
        for ($balance = 0; $balance <= 12000; $balance += 137) {
            for ($required = 100; $required <= 12000; $required += 971) {
                $deposit = Purchaser::requiredDepositAmount($balance, $required);

                $this->assertSame(0, $deposit % 1000);
                $this->assertGreaterThanOrEqual($required, $balance + $deposit);
            }
        }
    }
}
