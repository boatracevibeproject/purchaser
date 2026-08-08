<?php

declare(strict_types=1);

namespace BVP\Purchaser\Tests;

use BVP\Purchaser\Purchaser;
use BVP\Purchaser\PurchaserCore;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * @author shimomo
 */
final class PurchaserTest extends PHPUnitTestCase
{
    use TeleboatCredentials;

    /**
     * ファサードはシングルトンを抱えるので、テスト間に持ち越さない。
     *
     * @psalm-return void
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        Purchaser::resetInstance();
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    #[\Override]
    protected function tearDown(): void
    {
        Purchaser::resetInstance();
    }

    /**
     * ブラウザを起動せずに、静的呼び出しが本体へ委譲されることだけを確認する。
     *
     * @psalm-return void
     *
     * @return void
     */
    public function testStaticCallIsForwardedToTheCore(): void
    {
        Purchaser::createInstance(new PurchaserCore());

        $this->assertInstanceOf(PurchaserCore::class, Purchaser::setMaxTotalAmount(5000));
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testCreateInstanceReplacesTheSingleton(): void
    {
        $first = Purchaser::createInstance(new PurchaserCore());
        $second = Purchaser::createInstance(new PurchaserCore());

        $this->assertNotSame($first, $second);
        $this->assertSame($second, Purchaser::getInstance());
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testEnsureBalance(): void
    {
        $this->requireOptIn('PURCHASER_E2E_DEPOSIT');

        Purchaser::createInstance(new PurchaserCore());

        $balance = Purchaser::setSubscriberNumber($this->credential('SUBSCRIBER_NUMBER'))
            ->setPersonalIdentificationNumber($this->credential('PERSONAL_IDENTIFICATION_NUMBER'))
            ->setAuthenticationPassword($this->credential('AUTHENTICATION_PASSWORD'))
            ->setPurchasePassword($this->credential('PURCHASE_PASSWORD'))
            ->setArtifactDirectory(sys_get_temp_dir() . '/bvp-purchaser-artifacts')
            ->ensureBalance();

        $this->assertGreaterThanOrEqual(PurchaserCore::DEFAULT_TARGET_BALANCE, $balance);
    }
}
