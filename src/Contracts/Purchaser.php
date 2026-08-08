<?php

declare(strict_types=1);

namespace BVP\Purchaser\Contracts;

/**
 * @author shimomo
 */
interface Purchaser
{
    public static function getInstance(?PurchaserCore $purchaserCore = null): Purchaser;

    public static function createInstance(?PurchaserCore $purchaserCore = null): Purchaser;

    public static function resetInstance(): void;
}
