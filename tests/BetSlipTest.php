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
     * @psalm-return void
     *
     * @return void
     */
    public function testSingleDigitFocusIsAcceptedEvenThoughPhpCastsTheKeyToInt(): void
    {
        $slip = BetSlip::fromFocuses(['1' => 100, '4' => 200], betType: 1);

        $this->assertSame([['legs' => ['1'], 'amount' => 100], ['legs' => ['4'], 'amount' => 200]], $slip->bets);
        $this->assertSame(300, $slip->totalAmount);
        $this->assertSame(2, $slip->count());
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testTrifectaFocusesAreParsedAndTotalled(): void
    {
        $slip = BetSlip::fromFocuses(['1-2-3' => 100, '1-2-4' => 300], betType: 6);

        $this->assertSame(['1', '2', '3'], $slip->bets[0]['legs']);
        $this->assertSame(['1', '2', '4'], $slip->bets[1]['legs']);
        $this->assertSame(400, $slip->totalAmount);
    }

    /**
     * 着順を問わない賭式は `=` 区切りで届く。
     *
     * @psalm-return void
     *
     * @return void
     */
    public function testCombinationDelimiterIsAccepted(): void
    {
        $slip = BetSlip::fromFocuses(['1=2' => 100, '2=3' => 100], betType: 4);

        $this->assertSame(['1', '2'], $slip->bets[0]['legs']);
        $this->assertSame(200, $slip->totalAmount);
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testCombinationTreatsReversedOrderAsDuplicate(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('同じ買い目が重複しています');

        BetSlip::fromFocuses(['1=2' => 100, '2=1' => 100], betType: 4);
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testExactaKeepsOrderAndAllowsBothDirections(): void
    {
        $slip = BetSlip::fromFocuses(['1-2' => 100, '2-1' => 100], betType: 3);

        $this->assertSame(2, $slip->count());
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testLegCountMustMatchBetType(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('組番の要素数が賭式と一致しません');

        BetSlip::fromFocuses(['1-2' => 100], betType: 6);
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testAmountMustBeMultipleOfOneHundred(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('100円単位');

        BetSlip::fromFocuses(['1-2-3' => 150], betType: 6);
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testAmountMustReachOneHundred(): void
    {
        $this->expectException(PurchaserException::class);

        BetSlip::fromFocuses(['1-2-3' => 0], betType: 6);
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testLegOutOfRangeIsRejected(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('1〜6 以外');

        BetSlip::fromFocuses(['1-2-7' => 100], betType: 6);
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testRepeatedBoatNumberIsRejected(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('同じ艇番が重複');

        BetSlip::fromFocuses(['1-1-2' => 100], betType: 6);
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testEmptyFocusesIsRejected(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('買い目が空です');

        BetSlip::fromFocuses([], betType: 6);
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testUnknownBetTypeIsRejected(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('賭式が不正です');

        /** @psalm-suppress InvalidArgument */
        BetSlip::fromFocuses(['1-2-3' => 100], betType: 8);
    }
}
