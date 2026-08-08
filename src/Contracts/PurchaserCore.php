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
     * @psalm-param int<1000, max> $maxDepositAmount
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param int $maxDepositAmount
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setMaxDepositAmount(int $maxDepositAmount): \BVP\Purchaser\PurchaserCore;

    /**
     * @psalm-param int<100, max> $maxTotalAmount
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param int $maxTotalAmount
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setMaxTotalAmount(int $maxTotalAmount): \BVP\Purchaser\PurchaserCore;

    /**
     * @psalm-param non-empty-string $subscriberNumber
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param string $subscriberNumber
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setSubscriberNumber(string $subscriberNumber): \BVP\Purchaser\PurchaserCore;

    /**
     * @psalm-param non-empty-string $personalIdentificationNumber
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param string $personalIdentificationNumber
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setPersonalIdentificationNumber(string $personalIdentificationNumber): \BVP\Purchaser\PurchaserCore;

    /**
     * @psalm-param non-empty-string $authenticationPassword
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param string $authenticationPassword
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setAuthenticationPassword(string $authenticationPassword): \BVP\Purchaser\PurchaserCore;

    /**
     * @psalm-param non-empty-string $purchasePassword
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param string $purchasePassword
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setPurchasePassword(string $purchasePassword): \BVP\Purchaser\PurchaserCore;

    /**
     * @psalm-param ?\DateTimeInterface $deadline
     * @psalm-param int<0, max> $marginSeconds
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param ?\DateTimeInterface $deadline
     * @param int $marginSeconds
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setDeadline(?DateTimeInterface $deadline, int $marginSeconds = 5): \BVP\Purchaser\PurchaserCore;

    /**
     * @psalm-param ?non-empty-string $artifactDirectory
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param ?string $artifactDirectory
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setArtifactDirectory(?string $artifactDirectory): \BVP\Purchaser\PurchaserCore;

    /**
     * @psalm-param non-empty-string $lockPath
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param string $lockPath
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setLockPath(string $lockPath): \BVP\Purchaser\PurchaserCore;

    /**
     * @psalm-return int
     *
     * @return int
     */
    public function balance(): int;

    /**
     * @psalm-param int $required
     * @psalm-return int
     *
     * @param int $required
     * @return int
     */
    public function ensureBalance(int $required = \BVP\Purchaser\PurchaserCore::DEFAULT_TARGET_BALANCE): int;

    /**
     * @psalm-param int $stadiumNumber
     * @psalm-param int $number
     * @psalm-param int $type
     * @psalm-param non-empty-array<array-key, int> $focuses
     * @psalm-return \BVP\Purchaser\Receipt
     *
     * @param int $stadiumNumber
     * @param int $number
     * @param int $type
     * @param array $focuses
     * @return \BVP\Purchaser\Receipt
     */
    public function purchase(int $stadiumNumber, int $number, int $type, array $focuses): Receipt;
}
