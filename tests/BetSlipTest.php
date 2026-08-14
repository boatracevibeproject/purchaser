<?php

declare(strict_types=1);

namespace BVP\Purchaser\Tests;

use BVP\Purchaser\BetSlip;
use BVP\Purchaser\PurchaserException;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * @author shimomo
 */
final class BetSlipTest extends PHPUnitTestCase
{
    /**
     * PHP は数値文字列の配列キーを int にキャストするため、単勝の '1' は int(1) で届く。
     * declare(strict_types=1) 下で preg_split() に渡すと TypeError になっていた。
     *
     * @return void
     */
    public function test_single_digit_focus_is_accepted_even_though_php_casts_the_key_to_int(): void
    {
        $slip = BetSlip::fromFocuses(['1' => 100, '4' => 200], betType: 1);

        $this->assertSame([['legs' => ['1'], 'amount' => 100], ['legs' => ['4'], 'amount' => 200]], $slip->bets);
        $this->assertSame(300, $slip->totalAmount);
        $this->assertSame(2, $slip->count());
    }
    /**
     * @return void
     */

    public function test_trifecta_focuses_are_parsed_and_totalled(): void
    {
        $slip = BetSlip::fromFocuses(['1-2-3' => 100, '1-2-4' => 300], betType: 6);

        $this->assertSame(['1', '2', '3'], $slip->bets[0]['legs']);
        $this->assertSame(['1', '2', '4'], $slip->bets[1]['legs']);
        $this->assertSame(400, $slip->totalAmount);
    }

    /**
     * 着順を問わない賭式は `=` 区切りで届く。
     *
     * @return void
     */
    public function test_combination_delimiter_is_accepted(): void
    {
        $slip = BetSlip::fromFocuses(['1=2' => 100, '2=3' => 100], betType: 4);

        $this->assertSame(['1', '2'], $slip->bets[0]['legs']);
        $this->assertSame(200, $slip->totalAmount);
    }
    /**
     * @return void
     */

    public function test_combination_treats_reversed_order_as_duplicate(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('同じ買い目が重複しています');

        BetSlip::fromFocuses(['1=2' => 100, '2=1' => 100], betType: 4);
    }
    /**
     * @return void
     */

    public function test_exacta_keeps_order_and_allows_both_directions(): void
    {
        $slip = BetSlip::fromFocuses(['1-2' => 100, '2-1' => 100], betType: 3);

        $this->assertSame(2, $slip->count());
    }
    /**
     * @return void
     */

    public function test_leg_count_must_match_bet_type(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('組番の要素数が賭式と一致しません');

        BetSlip::fromFocuses(['1-2' => 100], betType: 6);
    }
    /**
     * @return void
     */

    public function test_amount_must_be_multiple_of_one_hundred(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('100円単位');

        BetSlip::fromFocuses(['1-2-3' => 150], betType: 6);
    }
    /**
     * @return void
     */

    public function test_amount_must_reach_one_hundred(): void
    {
        $this->expectException(PurchaserException::class);

        BetSlip::fromFocuses(['1-2-3' => 0], betType: 6);
    }
    /**
     * @return void
     */

    public function test_leg_out_of_range_is_rejected(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('1〜6 以外');

        BetSlip::fromFocuses(['1-2-7' => 100], betType: 6);
    }
    /**
     * @return void
     */

    public function test_repeated_boat_number_is_rejected(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('同じ艇番が重複');

        BetSlip::fromFocuses(['1-1-2' => 100], betType: 6);
    }
    /**
     * @return void
     */

    public function test_empty_focuses_is_rejected(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('買い目が空です');

        BetSlip::fromFocuses([], betType: 6);
    }
    /**
     * @return void
     */

    public function test_unknown_bet_type_is_rejected(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('賭式が不正です');

        /** @psalm-suppress InvalidArgument */
        BetSlip::fromFocuses(['1-2-3' => 100], betType: 8);
    }
}
