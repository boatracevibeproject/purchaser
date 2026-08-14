<?php

declare(strict_types=1);

namespace BVP\Purchaser;

/**
 * @method static \BVP\Purchaser\PurchaserCore setMaxDepositAmount(int $maxDepositAmount)
 * @method static \BVP\Purchaser\PurchaserCore setMaxTotalAmount(int $maxTotalAmount)
 * @method static \BVP\Purchaser\PurchaserCore setMaxBatchAmount(?int $maxBatchAmount)
 * @method static \BVP\Purchaser\PurchaserCore setSubscriberNumber(string $subscriberNumber)
 * @method static \BVP\Purchaser\PurchaserCore setPersonalIdentificationNumber(string $personalIdentificationNumber)
 * @method static \BVP\Purchaser\PurchaserCore setAuthenticationPassword(string $authenticationPassword)
 * @method static \BVP\Purchaser\PurchaserCore setPurchasePassword(string $purchasePassword)
 * @method static \BVP\Purchaser\PurchaserCore setDeadline(?\DateTimeInterface $deadline, int $marginSeconds = 5)
 * @method static \BVP\Purchaser\PurchaserCore setArtifactDirectory(?string $artifactDirectory)
 * @method static \BVP\Purchaser\PurchaserCore setLockPath(string $lockPath)
 * @method static int balance()
 * @method static int ensureBalance(int $required = \BVP\Purchaser\PurchaserCore::DEFAULT_TARGET_BALANCE)
 * @method static \BVP\Purchaser\Receipt purchase(int $stadiumNumber, int $number, int $type, array $focuses)
 * @method static \BVP\Purchaser\BatchReceipt purchaseMany(array $races)
 *
 * @author shimomo
 */
final class Purchaser implements Contracts\Purchaser
{
    private static ?Contracts\Purchaser $instance = null;
    /**
     * @param \BVP\Purchaser\Contracts\PurchaserCore $purchaser
     */

    public function __construct(private readonly Contracts\PurchaserCore $purchaser)
    {
        //
    }

    /**
     * @param non-empty-string $name
     * @param list<mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->purchaser->$name(...$arguments);
    }

    /**
     * @param non-empty-string $name
     * @param list<mixed> $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return self::getInstance()->$name(...$arguments);
    }

    /**
     * @param ?non-empty-string $seleniumServerUrl
     * @param ?int<1000, max> $maxDepositAmount
     * @param ?non-empty-string $subscriberNumber
     * @param ?non-empty-string $personalIdentificationNumber
     * @param ?non-empty-string $authenticationPassword
     * @param ?non-empty-string $purchasePassword
     * @param ?\BVP\Purchaser\Contracts\PurchaserCore $purchaserCore
     * @return \BVP\Purchaser\Contracts\Purchaser
     */
    #[\Override]
    public static function getInstance(
        ?Contracts\PurchaserCore $purchaserCore = null,
        ?string $seleniumServerUrl = null,
        ?int $maxDepositAmount = PurchaserCore::DEFAULT_MAX_DEPOSIT_AMOUNT,
        #[\SensitiveParameter] ?string $subscriberNumber = null,
        #[\SensitiveParameter] ?string $personalIdentificationNumber = null,
        #[\SensitiveParameter] ?string $authenticationPassword = null,
        #[\SensitiveParameter] ?string $purchasePassword = null
    ): Contracts\Purchaser {
        return self::$instance ??= new self(
            $purchaserCore ?? new PurchaserCore(
                $seleniumServerUrl,
                $maxDepositAmount,
                $subscriberNumber,
                $personalIdentificationNumber,
                $authenticationPassword,
                $purchasePassword
            )
        );
    }

    /**
     * @param ?non-empty-string $seleniumServerUrl
     * @param ?int<1000, max> $maxDepositAmount
     * @param ?non-empty-string $subscriberNumber
     * @param ?non-empty-string $personalIdentificationNumber
     * @param ?non-empty-string $authenticationPassword
     * @param ?non-empty-string $purchasePassword
     * @param ?\BVP\Purchaser\Contracts\PurchaserCore $purchaserCore
     * @return \BVP\Purchaser\Contracts\Purchaser
     */
    #[\Override]
    public static function createInstance(
        ?Contracts\PurchaserCore $purchaserCore = null,
        ?string $seleniumServerUrl = null,
        ?int $maxDepositAmount = PurchaserCore::DEFAULT_MAX_DEPOSIT_AMOUNT,
        #[\SensitiveParameter] ?string $subscriberNumber = null,
        #[\SensitiveParameter] ?string $personalIdentificationNumber = null,
        #[\SensitiveParameter] ?string $authenticationPassword = null,
        #[\SensitiveParameter] ?string $purchasePassword = null
    ): Contracts\Purchaser {
        return self::$instance = new self(
            $purchaserCore ?? new PurchaserCore(
                $seleniumServerUrl,
                $maxDepositAmount,
                $subscriberNumber,
                $personalIdentificationNumber,
                $authenticationPassword,
                $purchasePassword
            )
        );
    }
    /**
     * @return void
     */

    #[\Override]
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
