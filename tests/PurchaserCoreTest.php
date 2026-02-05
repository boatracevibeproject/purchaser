<?php

declare(strict_types=1);

namespace BVP\Purchaser\Tests;

use BVP\Purchaser\PurchaserCore;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * @author shimomo
 */
final class PurchaserCoreTest extends PHPUnitTestCase
{
    /**
     * @psalm-suppress PropertyNotSetInConstructor
     * @psalm-var \BVP\Purchaser\PurchaserCore
     *
     * @var \BVP\Purchaser\PurchaserCore
     */
    protected PurchaserCore $purchaser;

    /**
     * @psalm-return void
     *
     * @return void
     */
    #[\Override]
    public function setUp(): void
    {
        $this->purchaser = new PurchaserCore();
    }

    /**
     * @doesNotPerformAssertions
     *
     * @psalm-return void
     *
     * @return void
     */
    public function testPurchaser(): void
    {
        $subscriberNumber = $_ENV['SUBSCRIBER_NUMBER'];
        if ($subscriberNumber === '') {
            return;
        }

        $personalIdentificationNumber = $_ENV['PERSONAL_IDENTIFICATION_NUMBER'];
        if ($personalIdentificationNumber === '') {
            return;
        }

        $authenticationPassword = $_ENV['AUTHENTICATION_PASSWORD'];
        if ($authenticationPassword === '') {
            return;
        }

        $purchasePassword = $_ENV['PURCHASE_PASSWORD'];
        if ($purchasePassword === '') {
            return;
        }

        $this->purchaser
            ->setDepositAmount(1000)
            ->setSubscriberNumber($subscriberNumber)
            ->setPersonalIdentificationNumber($personalIdentificationNumber)
            ->setAuthenticationPassword($authenticationPassword)
            ->setPurchasePassword($purchasePassword)
            ->purchase(stadiumNumber: 24, number: 12, type: 6, focuses: [
                '1-2-3' => 500,
                '1-2-4' => 500,
            ]);
    }
}
