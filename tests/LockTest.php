<?php

declare(strict_types=1);

namespace BVP\Purchaser\Tests;

use BVP\Purchaser\Lock;
use BVP\Purchaser\PurchaserException;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * @author shimomo
 */
final class LockTest extends PHPUnitTestCase
{
    /**
     * @psalm-var string
     *
     * @var string
     */
    private string $path = '';

    /**
     * @psalm-return void
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/bvp-purchaser-test-' . bin2hex(random_bytes(8)) . '.lock';
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    #[\Override]
    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testSecondAcquisitionFailsWhileTheFirstIsHeld(): void
    {
        $first = new Lock($this->path);
        $first->acquire();

        $second = new Lock($this->path, timeoutSeconds: 0.5);

        try {
            $this->expectException(PurchaserException::class);
            $this->expectExceptionMessage('ロックを取得できませんでした');

            $second->acquire();
        } finally {
            $first->release();
        }
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testLockIsReusableAfterRelease(): void
    {
        $first = new Lock($this->path);
        $first->acquire();
        $first->release();

        $second = new Lock($this->path, timeoutSeconds: 0.5);
        $second->acquire();
        $second->release();

        $this->assertFileExists($this->path);
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function testAcquireIsIdempotent(): void
    {
        $lock = new Lock($this->path);
        $lock->acquire();
        $lock->acquire();
        $lock->release();

        $this->assertFileExists($this->path);
    }
}
