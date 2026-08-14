<?php

declare(strict_types=1);

namespace BVP\Purchaser;

use BVP\Purchaser\Enums\BetType;

/**
 * 買い目（focuses）の解釈・検証・合計金額の算出。
 *
 * WebDriver に一切依存しないので、Selenium も実弾も無しで単体テストできる。
 * 購入前に投げられる例外は、原則すべてここで投げ切ることを狙っている。
 *
 * @author shimomo
 */
final readonly class BetSlip
{
    /**
     * 購入金額の最小単位（円）。
     *
     * @var positive-int
     */
    public const int AMOUNT_UNIT = 100;

    /**
     * @param int $betType
     * @param non-empty-list<array{legs: non-empty-list<string>, amount: int}> $bets
     * @param positive-int $totalAmount
     */
    private function __construct(
        public int $betType,
        public array $bets,
        public int $totalAmount
    ) {
        //
    }

    /**
     * $focuses は 組番 => 購入金額。
     *
     * @param array<array-key, mixed> $focuses
     * @param int $betType
     * @return self
     *
     * @throws \BVP\Purchaser\PurchaserException 賭式・組番・金額のいずれかが不正なとき
     */
    public static function fromFocuses(array $focuses, int $betType): self
    {
        $type = BetType::tryFrom($betType);

        if ($type === null) {
            throw new PurchaserException("賭式が不正です。type={$betType}（1〜7）");
        }

        if ($focuses === []) {
            throw new PurchaserException('買い目が空です。');
        }

        $expectedLegCount = $type->legCount();
        $bets = [];
        $totalAmount = 0;
        $seen = [];

        foreach ($focuses as $focus => $amount) {
            // PHP は数値文字列の配列キーを int にキャストするため、
            // 単勝・複勝の '1' は int(1) で届く。文字列に戻してから解釈する。
            $focus = (string) $focus;

            if (!is_int($amount)) {
                throw new PurchaserException("購入金額が整数ではありません。組番 {$focus}");
            }

            if ($amount < self::AMOUNT_UNIT || $amount % self::AMOUNT_UNIT !== 0) {
                throw new PurchaserException(
                    "購入金額は100円単位かつ100円以上である必要があります。組番 {$focus} / 金額 {$amount}"
                );
            }

            $legs = preg_split('/[-=]/', $focus);

            if ($legs === false || count($legs) !== $expectedLegCount) {
                throw new PurchaserException(
                    "組番の要素数が賭式と一致しません。組番 {$focus} / type={$betType}（期待 {$expectedLegCount} 要素）"
                );
            }

            foreach ($legs as $leg) {
                if (preg_match('/\A[1-6]\z/', $leg) !== 1) {
                    throw new PurchaserException("組番に 1〜6 以外が含まれています。組番 {$focus}");
                }
            }

            if (count(array_unique($legs)) !== count($legs)) {
                throw new PurchaserException("組番に同じ艇番が重複しています。組番 {$focus}");
            }

            $key = self::deduplicationKey($legs, $type);

            if (isset($seen[$key])) {
                throw new PurchaserException("同じ買い目が重複しています。組番 {$focus}");
            }

            $seen[$key] = true;
            $bets[] = ['legs' => $legs, 'amount' => $amount];
            $totalAmount += $amount;
        }

        return new self($type->value, $bets, $totalAmount);
    }

    /**
     * 買い目の点数。
     *
     * @return positive-int
     */
    public function count(): int
    {
        return count($this->bets);
    }

    /**
     * 着順を問わない賭式では 1=2 と 2=1 を同一視する。
     *
     * @param non-empty-list<string> $legs
     * @param \BVP\Purchaser\Enums\BetType $betType
     * @return non-empty-string
     */
    private static function deduplicationKey(array $legs, BetType $betType): string
    {
        if ($betType->isCombination()) {
            sort($legs);

            return implode('=', $legs);
        }

        return implode('-', $legs);
    }
}
