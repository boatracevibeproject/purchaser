<?php

declare(strict_types=1);

namespace BVP\Purchaser\Contracts;

use BVP\Purchaser\Receipt;
use DateTimeInterface;

/**
 * @author shimomo
 */
interface PurchaserCore
{
    /**
     * @param  int<1000, max>  $maxDepositAmount
     */
    public function setMaxDepositAmount(int $maxDepositAmount): \BVP\Purchaser\PurchaserCore;

    /**
     * @param  int<100, max>  $maxTotalAmount
     */
    public function setMaxTotalAmount(int $maxTotalAmount): \BVP\Purchaser\PurchaserCore;

    /**
     * @param  non-empty-string  $subscriberNumber
     */
    public function setSubscriberNumber(string $subscriberNumber): \BVP\Purchaser\PurchaserCore;

    /**
     * @param  non-empty-string  $personalIdentificationNumber
     */
    public function setPersonalIdentificationNumber(string $personalIdentificationNumber): \BVP\Purchaser\PurchaserCore;

    /**
     * @param  non-empty-string  $authenticationPassword
     */
    public function setAuthenticationPassword(string $authenticationPassword): \BVP\Purchaser\PurchaserCore;

    /**
     * @param  non-empty-string  $purchasePassword
     */
    public function setPurchasePassword(string $purchasePassword): \BVP\Purchaser\PurchaserCore;

    /**
     * @param  int<0, max>  $marginSeconds
     */
    public function setDeadline(?DateTimeInterface $deadline, int $marginSeconds = 5): \BVP\Purchaser\PurchaserCore;

    /**
     * @param  ?non-empty-string  $artifactDirectory
     */
    public function setArtifactDirectory(?string $artifactDirectory): \BVP\Purchaser\PurchaserCore;

    /**
     * @param  non-empty-string  $lockPath
     */
    public function setLockPath(string $lockPath): \BVP\Purchaser\PurchaserCore;

    public function balance(): int;

    public function ensureBalance(int $required = \BVP\Purchaser\PurchaserCore::DEFAULT_TARGET_BALANCE): int;

    /**
     * @param  non-empty-array<array-key, int>  $focuses
     */
    public function purchase(int $stadiumNumber, int $number, int $type, array $focuses): Receipt;
}
