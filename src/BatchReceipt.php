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
     * @param  non-empty-list<Receipt>  $receipts  投票できたレース
     * @param  list<array{stadiumNumber: int, raceNumber: int, reason: string}>  $skipped  投票しなかったレースと理由
     * @param  ?array{stadiumNumber: int, raceNumber: int, reason: string}  $failure  以降を中止させた失敗
     * @param  int  $totalAmount  投票できたレースの購入金額合計
     * @param  int  $elapsedMilliseconds  purchaseMany() 全体の所要時間
     * @param  array<string, int>  $stepMilliseconds  セッション単位のステップ（ログイン・残高確保）
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
                static fn (Receipt $receipt): array => $receipt->toArray(),
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
