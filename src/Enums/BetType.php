<?php

declare(strict_types=1);

namespace BVP\Purchaser\Enums;

/**
 * 賭式。値はテレボートの勝式コードに一致する。
 *
 * ケース名を日本語にできないので（2連単のように数字で始まる識別子は書けない）、
 * Grade と同じくラテン文字の名前を使い、対応する日本語を行末に添える。
 *
 * @author shimomo
 */
enum BetType: int
{
    case WIN = 1;      // 単勝
    case PLACE = 2;    // 複勝
    case EXACTA = 3;   // 2連単
    case QUINELLA = 4; // 2連複
    case WIDE = 5;     // 拡連複
    case TRIFECTA = 6; // 3連単
    case TRIO = 7;     // 3連複

    /**
     * 組番の要素数。
     *
     * @return int<1, 3>
     */
    public function legCount(): int
    {
        return match ($this) {
            self::WIN, self::PLACE => 1,
            self::EXACTA, self::QUINELLA, self::WIDE => 2,
            self::TRIFECTA, self::TRIO => 3,
        };
    }

    /**
     * 着順を問わない賭式かどうか。組番の重複判定を順序に依存させないために使う。
     *
     * @return bool */
    public function isCombination(): bool
    {
        return match ($this) {
            self::QUINELLA, self::WIDE, self::TRIO => true,
            self::WIN, self::PLACE, self::EXACTA, self::TRIFECTA => false,
        };
    }
}
