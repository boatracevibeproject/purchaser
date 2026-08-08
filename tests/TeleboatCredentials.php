<?php

declare(strict_types=1);

namespace BVP\Purchaser\Tests;

/**
 * 結合テストのオプトイン判定と会員情報の取得。
 *
 * 旧実装は $_ENV を直接読み '' との比較でスキップしていたが、
 * 未設定のときはキー自体が無いので比較が成立せず、警告付きで素通りしていた。
 *
 * @author shimomo
 */
trait TeleboatCredentials
{
    /**
     * @psalm-param non-empty-string $variable
     * @psalm-return void
     *
     * @param string $variable
     * @return void
     */
    private function requireOptIn(string $variable): void
    {
        if ((string) getenv($variable) !== '1') {
            $this->markTestSkipped("{$variable}=1 が設定されていないためスキップします。");
        }

        $names = [
            'SUBSCRIBER_NUMBER',
            'PERSONAL_IDENTIFICATION_NUMBER',
            'AUTHENTICATION_PASSWORD',
            'PURCHASE_PASSWORD',
        ];

        foreach ($names as $name) {
            if ((string) getenv($name) === '') {
                $this->markTestSkipped("環境変数 {$name} が設定されていないためスキップします。");
            }
        }
    }

    /**
     * @psalm-param non-empty-string $name
     * @psalm-return non-empty-string
     *
     * @param string $name
     * @return string
     */
    private function credential(string $name): string
    {
        $value = (string) getenv($name);

        if ($value === '') {
            $this->markTestSkipped("環境変数 {$name} が設定されていません。");
        }

        return $value;
    }
}
