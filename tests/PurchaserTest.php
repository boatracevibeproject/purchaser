<?php

declare(strict_types=1);

namespace BVP\Purchaser\Tests;

use BVP\Purchaser\Purchaser;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * 静的な入口（instance / swap / forget）の振る舞い。
 *
 * @author shimomo
 */
final class PurchaserTest extends PHPUnitTestCase
{
    use TeleboatCredentials;

    /**
     * シングルトンを抱えるので、テスト間に持ち越さない。
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        Purchaser::forget();
    }

    /**
     * @return void
     */
    #[\Override]
    protected function tearDown(): void
    {
        Purchaser::forget();
    }

    /**
     * ブラウザを起動せずに、実体が1つだけ作られることを確認する。
     *
     * @return void
     */
    public function test_instance_returns_the_same_object(): void
    {
        $first = Purchaser::instance();

        $this->assertInstanceOf(Purchaser::class, $first);
        $this->assertSame($first, Purchaser::instance());
    }

    /**
     * @return void
     */
    public function test_swap_replaces_the_singleton(): void
    {
        $first = Purchaser::swap(new Purchaser());
        $second = Purchaser::swap(new Purchaser());

        $this->assertNotSame($first, $second);
        $this->assertSame($second, Purchaser::instance());
    }

    /**
     * @return void
     */
    public function test_forget_drops_the_singleton(): void
    {
        $first = Purchaser::instance();
        Purchaser::forget();

        $this->assertNotSame($first, Purchaser::instance());
    }

    /**
     * @return void
     */
    public function test_ensure_balance(): void
    {
        $this->requireOptIn('PURCHASER_E2E_DEPOSIT');

        $balance = Purchaser::instance()
            ->setSubscriberNumber($this->credential('SUBSCRIBER_NUMBER'))
            ->setPersonalIdentificationNumber($this->credential('PERSONAL_IDENTIFICATION_NUMBER'))
            ->setAuthenticationPassword($this->credential('AUTHENTICATION_PASSWORD'))
            ->setPurchasePassword($this->credential('PURCHASE_PASSWORD'))
            ->setArtifactDirectory(sys_get_temp_dir() . '/bvp-purchaser-artifacts')
            ->ensureBalance();

        $this->assertGreaterThanOrEqual(Purchaser::DEFAULT_TARGET_BALANCE, $balance);
    }
}
