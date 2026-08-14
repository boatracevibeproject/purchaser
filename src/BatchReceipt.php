<?php

declare(strict_types=1);

namespace BVP\Purchaser;

/**
 * 1セッションで処理した複数レースの控え。
 *
 * 途中で失敗しても、そこまでに投票できたレースの Receipt は必ず返す。
 * 例外にしてしまうと、既に動いたお金の記録が呼び出し側に残らない。
 *
 * @author shimomo
 */
final readonly class BatchReceipt
{
    /**
     * $receipts は投票できたレース、$skipped は投票しなかったレースと理由、
     * $failure は以降を中止させた失敗。$totalAmount は投票できたレースの購入金額合計、
     * $elapsedMilliseconds は purchaseMany() 全体の所要時間、$stepMilliseconds は
     * セッション単位のステップ（ログイン・残高確保）。
     *
     * @param non-empty-list<\BVP\Purchaser\Receipt> $receipts
     * @param list<array{stadiumNumber: int, raceNumber: int, reason: string}> $skipped
     * @param ?array{stadiumNumber: int, raceNumber: int, reason: string} $failure
     * @param int $totalAmount
     * @param int $elapsedMilliseconds
     * @param array<string, int> $stepMilliseconds
     */
    public function __construct(
        public array $receipts,
        public array $skipped,
        public ?array $failure,
        public int $totalAmount,
        public int $elapsedMilliseconds,
        public array $stepMilliseconds
    ) {
        //
    }

    /**
     * 投票できたレース数。
     *
     * @return positive-int
     */
    public function count(): int
    {
        return count($this->receipts);
    }

    /**
     * 途中で中止したかどうか。
     *
     * 例外ではなく戻り値で伝えるので、無人運転するならここを見て通知すること。
     *
     * @return bool
     */
    public function hasFailure(): bool
    {
        return $this->failure !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'receipts' => array_map(
                static fn(Receipt $receipt): array => $receipt->toArray(),
                $this->receipts
            ),
            'skipped' => $this->skipped,
            'failure' => $this->failure,
            'total_amount' => $this->totalAmount,
            'elapsed_milliseconds' => $this->elapsedMilliseconds,
            'step_milliseconds' => $this->stepMilliseconds,
        ];
    }
}
