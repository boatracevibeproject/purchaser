<?php

declare(strict_types=1);

namespace BVP\Purchaser;

/**
 * @psalm-method static \BVP\Purchaser\PurchaserCore setMaxDepositAmount(int $maxDepositAmount)
 * @psalm-method static \BVP\Purchaser\PurchaserCore setMaxTotalAmount(int $maxTotalAmount)
 * @psalm-method static \BVP\Purchaser\PurchaserCore setSubscriberNumber(string $subscriberNumber)
 * @psalm-method static \BVP\Purchaser\PurchaserCore setPersonalIdentificationNumber(string $personalIdentificationNumber)
 * @psalm-method static \BVP\Purchaser\PurchaserCore setAuthenticationPassword(string $authenticationPassword)
 * @psalm-method static \BVP\Purchaser\PurchaserCore setPurchasePassword(string $purchasePassword)
 * @psalm-method static \BVP\Purchaser\PurchaserCore setDeadline(?\DateTimeInterface $deadline, int $marginSeconds = 5)
 * @psalm-method static \BVP\Purchaser\PurchaserCore setArtifactDirectory(?string $artifactDirectory)
 * @psalm-method static \BVP\Purchaser\PurchaserCore setLockPath(string $lockPath)
 * @psalm-method static int balance()
 * @psalm-method static int ensureBalance(int $required = \BVP\Purchaser\PurchaserCore::DEFAULT_TARGET_BALANCE)
 * @psalm-method static \BVP\Purchaser\Receipt purchase(int $stadiumNumber, int $number, int $type, array $focuses)
 *
 * @method static \BVP\Purchaser\PurchaserCore setMaxDepositAmount(int $maxDepositAmount)
 * @method static \BVP\Purchaser\PurchaserCore setMaxTotalAmount(int $maxTotalAmount)
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
 *
 * @author shimomo
 */
final class Purchaser implements Contracts\Purchaser
{
    /**
     * @psalm-var ?\BVP\Purchaser\Contracts\Purchaser
     *
     * @var ?\BVP\Purchaser\Contracts\Purchaser
     */
    private static ?Contracts\Purchaser $instance = null;

    /**
     * @psalm-param \BVP\Purchaser\Contracts\PurchaserCore $purchaser
     *
     * @param \BVP\Purchaser\Contracts\PurchaserCore $purchaser
     */
    public function __construct(private readonly Contracts\PurchaserCore $purchaser)
    {
        //
    }

    /**
     * @psalm-param non-empty-string $name
     * @psalm-param list<mixed> $arguments
     * @psalm-return mixed
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->purchaser->$name(...$arguments);
    }

    /**
     * @psalm-param non-empty-string $name
     * @psalm-param list<mixed> $arguments
     * @psalm-return mixed
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return self::getInstance()->$name(...$arguments);
    }

    /**
     * @psalm-param ?\BVP\Purchaser\Contracts\PurchaserCore $purchaserCore
     * @psalm-param ?non-empty-string $seleniumServerUrl
     * @psalm-param ?int<1000, max> $maxDepositAmount
     * @psalm-param ?non-empty-string $subscriberNumber
     * @psalm-param ?non-empty-string $personalIdentificationNumber
     * @psalm-param ?non-empty-string $authenticationPassword
     * @psalm-param ?non-empty-string $purchasePassword
     * @psalm-return \BVP\Purchaser\Contracts\Purchaser
     *
     * @param ?\BVP\Purchaser\Contracts\PurchaserCore $purchaserCore
     * @param ?string $seleniumServerUrl
     * @param ?int $maxDepositAmount
     * @param ?string $subscriberNumber
     * @param ?string $personalIdentificationNumber
     * @param ?string $authenticationPassword
     * @param ?string $purchasePassword
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
     * @psalm-param ?\BVP\Purchaser\Contracts\PurchaserCore $purchaserCore
     * @psalm-param ?non-empty-string $seleniumServerUrl
     * @psalm-param ?int<1000, max> $maxDepositAmount
     * @psalm-param ?non-empty-string $subscriberNumber
     * @psalm-param ?non-empty-string $personalIdentificationNumber
     * @psalm-param ?non-empty-string $authenticationPassword
     * @psalm-param ?non-empty-string $purchasePassword
     * @psalm-return \BVP\Purchaser\Contracts\Purchaser
     *
     * @param ?\BVP\Purchaser\Contracts\PurchaserCore $purchaserCore
     * @param ?string $seleniumServerUrl
     * @param ?int $maxDepositAmount
     * @param ?string $subscriberNumber
     * @param ?string $personalIdentificationNumber
     * @param ?string $authenticationPassword
     * @param ?string $purchasePassword
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
     * @psalm-return void
     *
     * @return void
     */
    #[\Override]
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
