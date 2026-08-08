<?php

declare(strict_types=1);

namespace BVP\Purchaser;

use RuntimeException;

/**
 * このライブラリが投げる例外の基底。
 *
 * 呼び出し側が「購入経路の失敗」だけを個別に扱えるようにするために用意している。
 * WebDriver 由来の例外（TimeoutException 等）は、文脈を添えてこの例外に包み直す。
 *
 * @author shimomo
 */
final class PurchaserException extends RuntimeException
{
}
