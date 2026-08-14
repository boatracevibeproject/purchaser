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
     * @var string
     */
    private string $path = '';

    #[\Override]
    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/bvp-purchaser-test-' . bin2hex(random_bytes(8)) . '.lock';
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function test_second_acquisition_fails_while_the_first_is_held(): void
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

    public function test_lock_is_reusable_after_release(): void
    {
        $first = new Lock($this->path);
        $first->acquire();
        $first->release();

        $second = new Lock($this->path, timeoutSeconds: 0.5);
        $second->acquire();
        $second->release();

        $this->assertFileExists($this->path);
    }

    public function test_acquire_is_idempotent(): void
    {
        $lock = new Lock($this->path);
        $lock->acquire();
        $lock->acquire();
        $lock->release();

        $this->assertFileExists($this->path);
    }
}
