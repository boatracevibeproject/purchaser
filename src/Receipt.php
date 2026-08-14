<?php

declare(strict_types=1);

namespace BVP\Purchaser;

use DateTimeImmutable;

/**
 * 投票が受け付けられたことの控え。
 *
 * 呼び出し側が「何を・いくら・いつ買ったか」を永続化できるようにするために返す。
 * purchase() が void だと、成功したこと以外は何も記録に残せない。
 *
 * @author shimomo
 */
final readonly class Receipt
{
    /**
     * @param non-empty-list<array{legs: non-empty-list<string>, amount: int}> $bets
     * @param int $balanceBefore  投票直前の残高
     * @param int $elapsedMilliseconds  purchase() 全体の所要時間
     * @param array $stepMilliseconds  ステップ名 => 所要時間（m2 に間に合うかの実測用）
     * @param string $confirmationText  完了画面（thanksArea）のテキスト
     * @param ?string $receiptNumber  契約番号。読めなければ null
     * @param ?int $acceptedAmount  購入成立金額。読めなければ null
     * @param int $stadiumNumber
     * @param int $raceNumber
     * @param int $betType
     * @param int $totalAmount
     * @param \DateTimeImmutable $submittedAt
     */
    public function __construct(
        public int $stadiumNumber,
        public int $raceNumber,
        public int $betType,
        public array $bets,
        public int $totalAmount,
        public int $balanceBefore,
        public DateTimeImmutable $submittedAt,
        public int $elapsedMilliseconds,
        public array $stepMilliseconds,
        public string $confirmationText,
        public ?string $receiptNumber = null,
        public ?int $acceptedAmount = null
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stadium_number' => $this->stadiumNumber,
            'race_number' => $this->raceNumber,
            'bet_type' => $this->betType,
            'bets' => $this->bets,
            'total_amount' => $this->totalAmount,
            'balance_before' => $this->balanceBefore,
            'submitted_at' => $this->submittedAt->format(DateTimeImmutable::ATOM),
            'elapsed_milliseconds' => $this->elapsedMilliseconds,
            'step_milliseconds' => $this->stepMilliseconds,
            'confirmation_text' => $this->confirmationText,
            'receipt_number' => $this->receiptNumber,
            'accepted_amount' => $this->acceptedAmount,
        ];
    }
}
