<?php

declare(strict_types=1);

namespace BVP\Purchaser;

/**
 * @author shimomo
 */
interface PurchaserInterface
{
    /**
     * @psalm-param ?\BVP\Purchaser\PurchaserCoreInterface $purchaserCore
     * @psalm-return \BVP\Purchaser\PurchaserInterface
     *
     * @param ?\BVP\Purchaser\PurchaserCoreInterface $purchaserCore
     * @return \BVP\Purchaser\PurchaserInterface
     */
    public static function getInstance(?PurchaserCoreInterface $purchaserCore = null): PurchaserInterface;

    /**
     * @psalm-param ?\BVP\Purchaser\PurchaserCoreInterface $purchaserCore
     * @psalm-return \BVP\Purchaser\PurchaserInterface
     *
     * @param ?\BVP\Purchaser\PurchaserCoreInterface $purchaserCore
     * @return \BVP\Purchaser\PurchaserInterface
     */
    public static function createInstance(?PurchaserCoreInterface $purchaserCore = null): PurchaserInterface;

    /**
     * @psalm-return void
     *
     * @return void
     */
    public static function resetInstance(): void;
}
