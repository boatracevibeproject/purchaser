<?php

declare(strict_types=1);

namespace BVP\Purchaser\Contracts;

/**
 * @author shimomo
 */
interface Purchaser
{
    /**
     * @psalm-param ?\BVP\Purchaser\Contracts\PurchaserCore $purchaserCore
     * @psalm-return \BVP\Purchaser\Contracts\Purchaser
     *
     * @param ?\BVP\Purchaser\Contracts\PurchaserCore $purchaserCore
     * @return \BVP\Purchaser\Contracts\Purchaser
     */
    public static function getInstance(?PurchaserCore $purchaserCore = null): Purchaser;

    /**
     * @psalm-param ?\BVP\Purchaser\Contracts\PurchaserCore $purchaserCore
     * @psalm-return \BVP\Purchaser\Contracts\Purchaser
     *
     * @param ?\BVP\Purchaser\Contracts\PurchaserCore $purchaserCore
     * @return \BVP\Purchaser\Contracts\Purchaser
     */
    public static function createInstance(?PurchaserCore $purchaserCore = null): Purchaser;

    /**
     * @psalm-return void
     *
     * @return void
     */
    public static function resetInstance(): void;
}
