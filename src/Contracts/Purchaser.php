<?php

declare(strict_types=1);

namespace BVP\Purchaser\Contracts;

use BVP\Purchaser\BatchReceipt;
use BVP\Purchaser\Receipt;
use DateTimeInterface;

/**
 * 舟券を買う本体の契約。DI コンテナからはこれを Purchaser クラスへ束ねる。
 *
 * 静的な入口（instance など）は実装側にだけ置く。契約に静的メソッドを
 * 並べても、コンテナから使うぶんには何の役にも立たない。
 *
 * @author shimomo
 */
interface Purchaser
{
    /**
     * @param int<1000, max> $maxDepositAmount
     * @return \BVP\Purchaser\Purchaser
     */
    public function setMaxDepositAmount(int $maxDepositAmount): \BVP\Purchaser\Purchaser;

    /**
     * @param int<100, max> $maxTotalAmount
     * @return \BVP\Purchaser\Purchaser
     */
    public function setMaxTotalAmount(int $maxTotalAmount): \BVP\Purchaser\Purchaser;

    /**
     * @param ?int<100, max> $maxBatchAmount
     * @return \BVP\Purchaser\Purchaser
     */
    public function setMaxBatchAmount(?int $maxBatchAmount): \BVP\Purchaser\Purchaser;

    /**
     * @param non-empty-string $subscriberNumber
     * @return \BVP\Purchaser\Purchaser
     */
    public function setSubscriberNumber(string $subscriberNumber): \BVP\Purchaser\Purchaser;

    /**
     * @param non-empty-string $personalIdentificationNumber
     * @return \BVP\Purchaser\Purchaser
     */
    public function setPersonalIdentificationNumber(string $personalIdentificationNumber): \BVP\Purchaser\Purchaser;

    /**
     * @param non-empty-string $authenticationPassword
     * @return \BVP\Purchaser\Purchaser
     */
    public function setAuthenticationPassword(string $authenticationPassword): \BVP\Purchaser\Purchaser;

    /**
     * @param non-empty-string $purchasePassword
     * @return \BVP\Purchaser\Purchaser
     */
    public function setPurchasePassword(string $purchasePassword): \BVP\Purchaser\Purchaser;

    /**
     * @param int<0, max> $marginSeconds
     * @param ?\DateTimeInterface $deadline
     * @return \BVP\Purchaser\Purchaser
     */
    public function setDeadline(?DateTimeInterface $deadline, int $marginSeconds = 5): \BVP\Purchaser\Purchaser;

    /**
     * @param ?non-empty-string $artifactDirectory
     * @return \BVP\Purchaser\Purchaser
     */
    public function setArtifactDirectory(?string $artifactDirectory): \BVP\Purchaser\Purchaser;

    /**
     * @param non-empty-string $lockPath
     * @return \BVP\Purchaser\Purchaser
     */
    public function setLockPath(string $lockPath): \BVP\Purchaser\Purchaser;

    /**
     * @return int
     */
    public function balance(): int;

    /**
     * @param int $required
     * @return int
     */
    public function ensureBalance(int $required = \BVP\Purchaser\Purchaser::DEFAULT_TARGET_BALANCE): int;

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
