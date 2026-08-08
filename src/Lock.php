<?php

declare(strict_types=1);

namespace BVP\Purchaser;

/**
 * テレボートへのログインを排他するためのファイルロック。
 *
 * 朝の入金・締切前の投票・手動ログインが重なると、テレボート側でセッションが
 * 奪い合いになる。プロセスをまたいで直列化する必要があるので flock を使う。
 *
 * 締切に間に合わなくなるくらいなら待たずに落ちるほうがよいので、
 * 無期限に待たずタイムアウトで例外にする。
 *
 * @author shimomo
 */
final class Lock
{
    /**
     * @psalm-var ?resource
     *
     * @var mixed
     */
    private mixed $handle = null;

    /**
     * @psalm-param string $path
     * @psalm-param float $timeoutSeconds
     *
     * @param string $path
     * @param float $timeoutSeconds
     */
    public function __construct(
        private readonly string $path,
        private readonly float $timeoutSeconds = 30.0
    ) {
        //
    }

    /**
     * @psalm-return void
     *
     * @return void
     *
     * @throws \BVP\Purchaser\PurchaserException
     */
    public function acquire(): void
    {
        if ($this->handle !== null) {
            return;
        }

        $handle = @fopen($this->path, 'c');

        if ($handle === false) {
            throw new PurchaserException("ロックファイルを開けませんでした。path={$this->path}");
        }

        $deadline = microtime(true) + $this->timeoutSeconds;

        while (!flock($handle, LOCK_EX | LOCK_NB)) {
            if (microtime(true) >= $deadline) {
                fclose($handle);

                throw new PurchaserException(
                    "他のプロセスがテレボートを操作中のためロックを取得できませんでした。path={$this->path}"
                );
            }

            usleep(200_000);
        }

        $this->handle = $handle;
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function release(): void
    {
        if ($this->handle === null) {
            return;
        }

        $handle = $this->handle;
        $this->handle = null;

        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
