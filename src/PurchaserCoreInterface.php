<?php

declare(strict_types=1);

namespace BVP\Purchaser;

/**
 * @author shimomo
 */
interface PurchaserCoreInterface
{
    /**
     * @psalm-param int<1000, max> $depositAmount
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param int $depositAmount
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setDepositAmount(int $depositAmount): PurchaserCore;

    /**
     * @psalm-param non-empty-string $subscriberNumber
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param string $subscriberNumber
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setSubscriberNumber(string $subscriberNumber): PurchaserCore;

    /**
     * @psalm-param non-empty-string $personalIdentificationNumber
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param string $personalIdentificationNumber
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setPersonalIdentificationNumber(string $personalIdentificationNumber): PurchaserCore;

    /**
     * @psalm-param non-empty-string $authenticationPassword
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param string $authenticationPassword
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setAuthenticationPassword(string $authenticationPassword): PurchaserCore;

    /**
     * @psalm-param non-empty-string $purchasePassword
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param string $purchasePassword
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setPurchasePassword(string $purchasePassword): PurchaserCore;

    /**
     * @psalm-param int<1, 24> $stadiumNumber
     * @psalm-param int<1, 12> $number
     * @psalm-param int<1, 7> $type
     * @psalm-param non-empty-array<non-empty-string, int<100, max>> $focuses
     * @psalm-return void
     *
     * @param int $stadiumNumber
     * @param int $number
     * @param int $type
     * @param array $focuses
     * @return void
     */
    public function purchase(int $stadiumNumber, int $number, int $type, array $focuses): void;
}
