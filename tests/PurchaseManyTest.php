<?php

declare(strict_types=1);

namespace BVP\Purchaser\Tests;

use BVP\Purchaser\PurchaserCore;
use BVP\Purchaser\PurchaserException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * purchaseMany() が「ブラウザを起動する前に」落とす入力の検証。
 *
 * ここで例外になるものは Selenium も会員情報も無しで実行できる。
 * 逆に、検証を通ってしまう入力はブラウザを起動しにいくのでここには置かない。
 *
 * @author shimomo
 */
final class PurchaseManyTest extends PHPUnitTestCase
{
    public function test_empty_races_are_rejected(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('レースが空です。');

        /** @psalm-suppress ArgumentTypeCoercion 呼び出し側が空を渡しうるので、実行時に落ちることを確かめる */
        (new PurchaserCore())->purchaseMany([]);
    }

    /**
     * 締切の早いレースを後ろに置くと、前のレースを処理する間に締切をまたぐ。
     */
    public function test_races_out_of_deadline_order_are_rejected(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('レースが締切の早い順に並んでいません。');

        (new PurchaserCore())->purchaseMany([
            [
                'stadiumNumber' => 24,
                'number' => 12,
                'type' => 6,
                'focuses' => ['1-2-3' => 100],
                'deadline' => new DateTimeImmutable('2099-01-01 15:30:00'),
            ],
            [
                'stadiumNumber' => 24,
                'number' => 11,
                'type' => 6,
                'focuses' => ['1-2-4' => 100],
                'deadline' => new DateTimeImmutable('2099-01-01 15:00:00'),
            ],
        ]);
    }

    public function test_the_same_race_and_bet_type_twice_is_rejected(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('同じレース・同じ賭式が重複しています。');

        (new PurchaserCore())->purchaseMany([
            ['stadiumNumber' => 24, 'number' => 12, 'type' => 6, 'focuses' => ['1-2-3' => 100]],
            ['stadiumNumber' => 24, 'number' => 12, 'type' => 6, 'focuses' => ['1-2-4' => 100]],
        ]);
    }

    /**
     * 同じレースでも賭式が違えば別物なので通す。
     *
     * 検証を通ったことを、次に来る締切の判定で確かめる（ブラウザは起動しない）。
     */
    public function test_the_same_race_with_a_different_bet_type_passes_validation(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('purchase の開始 を中止しました。');

        (new PurchaserCore())->purchaseMany([
            [
                'stadiumNumber' => 24,
                'number' => 12,
                'type' => 6,
                'focuses' => ['1-2-3' => 100],
                'deadline' => new DateTimeImmutable('2000-01-01 15:00:00'),
            ],
            [
                'stadiumNumber' => 24,
                'number' => 12,
                'type' => 3,
                'focuses' => ['1-2' => 100],
                'deadline' => new DateTimeImmutable('2000-01-01 15:00:00'),
            ],
        ]);
    }

    /**
     * 1レースずつは上限内でも、合計で上限を超えるものを止める。
     */
    public function test_the_batch_limit_stops_the_sum_of_all_races(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('購入金額の合計 3000 円が1セッションの上限 2000 円を超えています。');

        (new PurchaserCore())
            ->setMaxTotalAmount(1000)
            ->setMaxBatchAmount(2000)
            ->purchaseMany([
                ['stadiumNumber' => 24, 'number' => 10, 'type' => 6, 'focuses' => ['1-2-3' => 1000]],
                ['stadiumNumber' => 24, 'number' => 11, 'type' => 6, 'focuses' => ['1-2-3' => 1000]],
                ['stadiumNumber' => 24, 'number' => 12, 'type' => 6, 'focuses' => ['1-2-3' => 1000]],
            ]);
    }

    /**
     * setMaxBatchAmount() を呼ばなければ、1レースの上限をそのまま合計の上限にする。
     *
     * 既定のまま N レース買って N 倍まで通ってしまうのが一番まずい。
     */
    public function test_the_batch_limit_falls_back_to_the_per_race_limit(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('1セッションの上限 ' . PurchaserCore::DEFAULT_MAX_TOTAL_AMOUNT . ' 円');

        (new PurchaserCore())->purchaseMany([
            ['stadiumNumber' => 24, 'number' => 11, 'type' => 6, 'focuses' => ['1-2-3' => 10000]],
            ['stadiumNumber' => 24, 'number' => 12, 'type' => 6, 'focuses' => ['1-2-3' => 10000]],
        ]);
    }

    public function test_the_per_race_limit_still_applies(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('1レースの上限 1000 円を超えています。');

        (new PurchaserCore())
            ->setMaxTotalAmount(1000)
            ->setMaxBatchAmount(100000)
            ->purchaseMany([
                ['stadiumNumber' => 24, 'number' => 11, 'type' => 6, 'focuses' => ['1-2-3' => 100]],
                ['stadiumNumber' => 24, 'number' => 12, 'type' => 6, 'focuses' => ['1-2-3' => 1100]],
            ]);
    }

    public function test_an_invalid_race_number_is_rejected(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('レース番号が不正です。number=13（1〜12）');

        (new PurchaserCore())->purchaseMany([
            ['stadiumNumber' => 24, 'number' => 12, 'type' => 6, 'focuses' => ['1-2-3' => 100]],
            ['stadiumNumber' => 24, 'number' => 13, 'type' => 6, 'focuses' => ['1-2-3' => 100]],
        ]);
    }

    /**
     * 買い目の検証は1レース目だけでなく全レースに掛かる。
     */
    public function test_a_broken_focus_in_a_later_race_is_rejected(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('組番の要素数が賭式と一致しません。');

        (new PurchaserCore())->purchaseMany([
            ['stadiumNumber' => 24, 'number' => 11, 'type' => 6, 'focuses' => ['1-2-3' => 100]],
            ['stadiumNumber' => 24, 'number' => 12, 'type' => 6, 'focuses' => ['1-2' => 100]],
        ]);
    }

    /**
     * 個別の締切が無いレースには setDeadline() の値を使う。
     */
    public function test_the_session_deadline_applies_to_races_without_one(): void
    {
        $this->expectException(PurchaserException::class);
        $this->expectExceptionMessage('purchase の開始 を中止しました。');

        (new PurchaserCore())
            ->setDeadline(new DateTimeImmutable('2000-01-01 15:00:00'))
            ->purchaseMany([
                ['stadiumNumber' => 24, 'number' => 12, 'type' => 6, 'focuses' => ['1-2-3' => 100]],
            ]);
    }
}
