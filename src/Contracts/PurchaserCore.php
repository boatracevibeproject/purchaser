<?php

declare(strict_types=1);

namespace BVP\Purchaser\Contracts;

use BVP\Purchaser\BatchReceipt;
use BVP\Purchaser\Receipt;
use DateTimeInterface;

/**
 * @author shimomo
 */
interface PurchaserCore
{
    /**
     * @param int<1000, max> $maxDepositAmount
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setMaxDepositAmount(int $maxDepositAmount): \BVP\Purchaser\PurchaserCore;

    /**
     * @param int<100, max> $maxTotalAmount
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setMaxTotalAmount(int $maxTotalAmount): \BVP\Purchaser\PurchaserCore;

    /**
     * @param ?int<100, max> $maxBatchAmount
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setMaxBatchAmount(?int $maxBatchAmount): \BVP\Purchaser\PurchaserCore;

    /**
     * @param non-empty-string $subscriberNumber
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setSubscriberNumber(string $subscriberNumber): \BVP\Purchaser\PurchaserCore;

    /**
     * @param non-empty-string $personalIdentificationNumber
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setPersonalIdentificationNumber(string $personalIdentificationNumber): \BVP\Purchaser\PurchaserCore;

    /**
     * @param non-empty-string $authenticationPassword
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setAuthenticationPassword(string $authenticationPassword): \BVP\Purchaser\PurchaserCore;

    /**
     * @param non-empty-string $purchasePassword
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setPurchasePassword(string $purchasePassword): \BVP\Purchaser\PurchaserCore;

    /**
     * @param int<0, max> $marginSeconds
     * @param ?\DateTimeInterface $deadline
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setDeadline(?DateTimeInterface $deadline, int $marginSeconds = 5): \BVP\Purchaser\PurchaserCore;

    /**
     * @param ?non-empty-string $artifactDirectory
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setArtifactDirectory(?string $artifactDirectory): \BVP\Purchaser\PurchaserCore;

    /**
     * @param non-empty-string $lockPath
     * @return \BVP\Purchaser\PurchaserCore
     */
    public function setLockPath(string $lockPath): \BVP\Purchaser\PurchaserCore;
    /**
     * @return int
     */

    public function balance(): int;
    /**
     * @param int $required
     * @return int
     */

    public function ensureBalance(int $required = \BVP\Purchaser\PurchaserCore::DEFAULT_TARGET_BALANCE): int;

    /**
     * @param non-empty-array<array-key, int> $focuses
     * @param int $stadiumNumber
     * @param int $number
     * @param int $type
     * @return \BVP\Purchaser\Receipt
     */
    public function purchase(int $stadiumNumber, int $number, int $type, array $focuses): Receipt;

    /**
     * @param non-empty-list<array{
     *     stadiumNumber: int,
     *     number: int,
     *     type: int,
     *     focuses: non-empty-array<array-key, int>,
     *     deadline?: ?DateTimeInterface
     * }>  $races
     * @return \BVP\Purchaser\BatchReceipt
     */
    public function purchaseMany(array $races): BatchReceipt;
}
