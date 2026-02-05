<?php

declare(strict_types=1);

namespace BVP\Purchaser;

/**
 * @psalm-method static \BVP\Purchaser\PurchaserCore setDepositAmount(int $depositAmount)
 * @psalm-method static \BVP\Purchaser\PurchaserCore setSubscriberNumber(string $subscriberNumber)
 * @psalm-method static \BVP\Purchaser\PurchaserCore setAuthenticationPassword(string $authenticationPassword)
 * @psalm-method static \BVP\Purchaser\PurchaserCore setPurchasePassword(string $purchasePassword)
 * @psalm-method static void purchase(int $stadiumNumber, int $number, int $type, array $focuses)
 *
 * @method static \BVP\Purchaser\PurchaserCore setDepositAmount(int $depositAmount)
 * @method static \BVP\Purchaser\PurchaserCore setSubscriberNumber(string $subscriberNumber)
 * @method static \BVP\Purchaser\PurchaserCore setAuthenticationPassword(string $authenticationPassword)
 * @method static \BVP\Purchaser\PurchaserCore setPurchasePassword(string $purchasePassword)
 * @method static void purchase(int $stadiumNumber, int $number, int $type, array $focuses)
 *
 * @author shimomo
 */
final class Purchaser implements PurchaserInterface
{
    /**
     * @psalm-var ?\BVP\Purchaser\PurchaserInterface
     *
     * @var ?\BVP\Purchaser\PurchaserInterface
     */
    private static ?PurchaserInterface $instance;

    /**
     * @psalm-param \BVP\Purchaser\PurchaserCoreInterface $purchaser
     *
     * @param \BVP\Purchaser\PurchaserCoreInterface $purchaser
     */
    public function __construct(private readonly PurchaserCoreInterface $purchaser)
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
     * @psalm-param ?\BVP\Purchaser\PurchaserCoreInterface $purchaserCore
     * @psalm-param ?non-empty-string $seleniumServerUrl
     * @psalm-param ?int<1000, max> $depositAmount
     * @psalm-param ?non-empty-string $subscriberNumber
     * @psalm-param ?non-empty-string $personalIdentificationNumber
     * @psalm-param ?non-empty-string $authenticationPassword
     * @psalm-param ?non-empty-string $purchasePassword
     * @psalm-return \BVP\Purchaser\PurchaserInterface
     *
     * @param ?\BVP\Purchaser\PurchaserCoreInterface $purchaserCore
     * @param ?string $seleniumServerUrl
     * @param ?int<1000, max> $depositAmount
     * @param ?string $subscriberNumber
     * @param ?string $personalIdentificationNumber
     * @param ?string $authenticationPassword
     * @param ?string $purchasePassword
     * @return \BVP\Purchaser\PurchaserInterface
     */
    #[\Override]
    public static function getInstance(
        ?PurchaserCoreInterface $purchaserCore = null,
        ?string $seleniumServerUrl = null,
        ?int $depositAmount = null,
        ?string $subscriberNumber = null,
        ?string $personalIdentificationNumber = null,
        ?string $authenticationPassword = null,
        ?string $purchasePassword = null
    ): PurchaserInterface {
        return self::$instance ??= new self(
            $purchaserCore ?? new PurchaserCore(
                $seleniumServerUrl,
                $depositAmount,
                $subscriberNumber,
                $personalIdentificationNumber,
                $authenticationPassword,
                $purchasePassword
            )
        );
    }

    /**
     * @psalm-param ?\BVP\Purchaser\PurchaserCoreInterface $purchaserCore
     * @psalm-param ?non-empty-string $seleniumServerUrl
     * @psalm-param ?int<1000, max> $depositAmount
     * @psalm-param ?non-empty-string $subscriberNumber
     * @psalm-param ?non-empty-string $personalIdentificationNumber
     * @psalm-param ?non-empty-string $authenticationPassword
     * @psalm-param ?non-empty-string $purchasePassword
     * @psalm-return \BVP\Purchaser\PurchaserInterface
     *
     * @param ?\BVP\Trimmer\PurchaserCoreInterface $purchaserCore
     * @param ?string $seleniumServerUrl
     * @param ?int<1000, max> $depositAmount
     * @param ?string $subscriberNumber
     * @param ?string $personalIdentificationNumber
     * @param ?string $authenticationPassword
     * @param ?string $purchasePassword
     * @return \BVP\Trimmer\PurchaserInterface
     */
    #[\Override]
    public static function createInstance(
        ?PurchaserCoreInterface $purchaserCore = null,
        ?string $seleniumServerUrl = null,
        ?int $depositAmount = null,
        ?string $subscriberNumber = null,
        ?string $personalIdentificationNumber = null,
        ?string $authenticationPassword = null,
        ?string $purchasePassword = null
    ): PurchaserInterface {
        return self::$instance = new self(
            $purchaserCore ?? new PurchaserCore(
                $seleniumServerUrl,
                $depositAmount,
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
