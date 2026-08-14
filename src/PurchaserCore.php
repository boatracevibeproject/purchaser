<?php

declare(strict_types=1);

namespace BVP\Purchaser;

use DateTimeImmutable;
use DateTimeInterface;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Exception\ElementClickInterceptedException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Throwable;

/**
 * @author shimomo
 */
final class PurchaserCore implements Contracts\PurchaserCore
{
    /**
     * 朝に満たしておく残高の既定値（円）。
     *
     * @var int
     */
    public const int DEFAULT_TARGET_BALANCE = 10000;

    /**
     * 1回の入金指示の上限（円）。想定外の巨額入金を防ぐ安全弁。
     *
     * @var int
     */
    public const int DEFAULT_MAX_DEPOSIT_AMOUNT = 10000;

    /**
     * 1レースあたりの購入金額合計の上限（円）。呼び出し側のバグを止める安全弁。
     *
     * @var int
     */
    public const int DEFAULT_MAX_TOTAL_AMOUNT = 10000;

    /**
     * 入金指示の最小単位（円）。
     *
     * @var int
     */
    private const int DEPOSIT_UNIT = 1000;

    /**
     * @var int
     */
    private const int WAIT_TIMEOUT_SECONDS = 10;

    /**
     * ポーリング間隔。既定の 500ms は 1 ステップあたり平均 250ms を捨てることになり、
     * 30 ステップ超のこのフローでは無視できない遅延になるため短くしている。
     *
     * @var int
     */
    private const int WAIT_INTERVAL_MILLISECONDS = 100;

    /**
     * 「出ていないのが正常」な要素を待つときのタイムアウト。
     *
     * @var int
     */
    private const int OPTIONAL_WAIT_TIMEOUT_SECONDS = 2;

    /**
     * 入金が残高に反映されるまで待つ上限。
     *
     * @var float
     */
    private const float DEPOSIT_REFLECTION_TIMEOUT_SECONDS = 60.0;

    /**
     * @var int
     */
    private const int DEPOSIT_REFLECTION_INTERVAL_MICROSECONDS = 500_000;

    /**
     * @var string
     */
    private const string LOGIN_URL = 'https://ib.mbrace.or.jp/';

    /**
     * ⚠ サイトの DOM に直結しているのでここだけを直せば追従できるようにまとめている。
     * とくに RACE_NUMBER は絶対パスに近い XPath で、DOM が少し変わるだけで壊れる。
     *
     * @var string
     */
    private const string LOCATOR_RACE_NUMBER
        = 'descendant-or-self::body/div[1]/main/div/div[1]/section[2]/div[2]/dl/dt/strong';

    /**
     * @var string
     */
    private const string LOCATOR_BALANCE = '.gray > .col3';

    /**
     * ヘッダの購入限度額。残高照会ダイアログの LOCATOR_BALANCE と同じ
     * currentBetLimitAmount を出しているので、レース間の残高確認はここで足りる。
     *
     * @var string
     */
    private const string LOCATOR_HEADER_BALANCE = '#currentBetLimitAmount';

    /**
     * 投票完了画面の契約番号・購入成立金額の入れ物。
     *
     * @var string
     */
    private const string LOCATOR_CONFIRMATION = '#confirmationArea';

    /**
     * 投票完了画面の「場を変更して投票する」。ログイン直後と同じ場選択画面に戻る。
     *
     * @var string
     */
    private const string LOCATOR_MODIFY_JYO_BET = 'modifyJyoBetForm';

    /**
     * @var ?\Facebook\WebDriver\Remote\RemoteWebDriver
     */
    private ?RemoteWebDriver $driver = null;

    /**
     * @var ?\DateTimeInterface
     */
    private ?DateTimeInterface $deadline = null;

    /**
     * @var int<0, max>
     */
    private int $deadlineMarginSeconds = 5;

    /**
     * @var ?non-empty-string
     */
    private ?string $artifactDirectory = null;

    /**
     * @var non-empty-string
     */
    private string $lockPath;

    /**
     * 1セッションの購入金額合計の上限（円）。null なら maxTotalAmount を流用する。
     *
     * @var ?int<100, max>
     */
    private ?int $maxBatchAmount = null;

    /**
     * @var array<string, int>
     */
    private array $stepMilliseconds = [];

    /**
     * $maxDepositAmount は1回の入金指示の上限。
     *
     * @param ?non-empty-string $seleniumServerUrl
     * @param ?int<1000, max> $maxDepositAmount
     * @param ?non-empty-string $subscriberNumber
     * @param ?non-empty-string $personalIdentificationNumber
     * @param ?non-empty-string $authenticationPassword
     * @param ?non-empty-string $purchasePassword
     * @param ?int $maxTotalAmount
     */
    public function __construct(
        private ?string $seleniumServerUrl = null,
        private ?int $maxDepositAmount = self::DEFAULT_MAX_DEPOSIT_AMOUNT,
        #[\SensitiveParameter] private ?string $subscriberNumber = null,
        #[\SensitiveParameter] private ?string $personalIdentificationNumber = null,
        #[\SensitiveParameter] private ?string $authenticationPassword = null,
        #[\SensitiveParameter] private ?string $purchasePassword = null,
        private ?int $maxTotalAmount = self::DEFAULT_MAX_TOTAL_AMOUNT
    ) {
        $this->lockPath = sys_get_temp_dir() . '/bvp-purchaser.lock';
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->closeSession();
    }

    /**
     * @param int<1000, max> $maxDepositAmount
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setMaxDepositAmount(int $maxDepositAmount): PurchaserCore
    {
        $this->maxDepositAmount = $maxDepositAmount;

        return $this;
    }

    /**
     * @param int<100, max> $maxTotalAmount
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setMaxTotalAmount(int $maxTotalAmount): PurchaserCore
    {
        $this->maxTotalAmount = $maxTotalAmount;

        return $this;
    }

    /**
     * 1セッション（purchaseMany の1回）の購入金額合計の上限。
     *
     * setMaxTotalAmount() は1レースの上限なので、N レース買えば N 倍まで通ってしまう。
     * null を渡すと maxTotalAmount をそのまま流用する。
     *
     * @param ?int<100, max> $maxBatchAmount
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setMaxBatchAmount(?int $maxBatchAmount): PurchaserCore
    {
        $this->maxBatchAmount = $maxBatchAmount;

        return $this;
    }

    /**
     * @param non-empty-string $subscriberNumber
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setSubscriberNumber(#[\SensitiveParameter] string $subscriberNumber): PurchaserCore
    {
        $this->subscriberNumber = $subscriberNumber;

        return $this;
    }

    /**
     * @param non-empty-string $personalIdentificationNumber
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setPersonalIdentificationNumber(
        #[\SensitiveParameter] string $personalIdentificationNumber
    ): PurchaserCore {
        $this->personalIdentificationNumber = $personalIdentificationNumber;

        return $this;
    }

    /**
     * @param non-empty-string $authenticationPassword
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setAuthenticationPassword(#[\SensitiveParameter] string $authenticationPassword): PurchaserCore
    {
        $this->authenticationPassword = $authenticationPassword;

        return $this;
    }

    /**
     * @param non-empty-string $purchasePassword
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setPurchasePassword(#[\SensitiveParameter] string $purchasePassword): PurchaserCore
    {
        $this->purchasePassword = $purchasePassword;

        return $this;
    }

    /**
     * 締切時刻を渡すと、間際になった時点で「投票せずに」中止する。
     *
     * 締切をまたいで submit すると、弾かれるか最悪は次のレースに入る。
     * 入金だけ済んで投票していない状態（残高に残るだけ）に倒すのが安全側。
     *
     * @param ?\DateTimeInterface $deadline
     * @param int<0, max> $marginSeconds
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setDeadline(?DateTimeInterface $deadline, int $marginSeconds = 5): PurchaserCore
    {
        $this->deadline = $deadline;
        $this->deadlineMarginSeconds = $marginSeconds;

        return $this;
    }

    /**
     * 失敗時にスクリーンショットと HTML を保存するディレクトリ。
     *
     * 一発勝負で再現できないので、無人運転するなら必ず設定すること。
     *
     * @param ?non-empty-string $artifactDirectory
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setArtifactDirectory(?string $artifactDirectory): PurchaserCore
    {
        $this->artifactDirectory = $artifactDirectory;

        return $this;
    }

    /**
     * @param non-empty-string $lockPath
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setLockPath(string $lockPath): PurchaserCore
    {
        $this->lockPath = $lockPath;

        return $this;
    }

    /**
     * 残高照会のみ。投票も入金もしない。
     *
     * @return int
     */
    #[\Override]
    public function balance(): int
    {
        /** @var int */
        return $this->withSession(fn(): int => $this->fetchBalance(), 'balance');
    }

    /**
     * 残高を $required 円まで満たす。投票はしない。
     *
     * 「入金する」ではなく「この額まで満たす」形なので何度呼んでも結果が同じになる。
     * 朝のバッチから呼ぶことを想定していて、catch-up で二重に走っても積み増さない。
     *
     * 手順は次の4段で、入金エラーを発生させないために最後の再照会まで必ず通す。
     *   1. 必要額を確定する（引数）
     *   2. 残高照会
     *   3. 不足していれば、不足分を1,000円単位に切り上げて入金
     *   4. 残高照会で $required を満たしたことを確認（入金の反映は非同期）
     *
     * 残高が既に足りていれば手順3を飛ばす。このとき手順2の照会が手順4の確認を兼ねる。
     * 戻り値は最終的な残高。
     *
     * @param int $required
     * @return int
     *
     * @throws \BVP\Purchaser\PurchaserException
     */
    #[\Override]
    public function ensureBalance(int $required = self::DEFAULT_TARGET_BALANCE): int
    {
        /** @var int */
        return $this->withSession(fn(): int => $this->ensureBalanceInSession($required), 'ensure-balance');
    }

    /**
     * $number はレース番号（1〜12）、$focuses は 組番 => 購入金額。
     * $type は賭式で、単勝 => 1, 複勝 => 2, 2連単 => 3, 2連複 => 4,
     * 拡連複 => 5, 3連単 => 6, 3連複 => 7。
     *
     * @param int $stadiumNumber
     * @param int $number
     * @param int $type
     * @param non-empty-array<array-key, int> $focuses
     * @return \BVP\Purchaser\Receipt
     *
     * @throws \BVP\Purchaser\PurchaserException 投票できたレースが1つも無かったとき
     */
    #[\Override]
    public function purchase(int $stadiumNumber, int $number, int $type, array $focuses): Receipt
    {
        $batch = $this->purchaseMany([[
            'stadiumNumber' => $stadiumNumber,
            'number' => $number,
            'type' => $type,
            'focuses' => $focuses,
            'deadline' => $this->deadline,
        ]]);

        // 1レースも投票できなければ purchaseMany() が例外を投げているので、ここは必ず埋まる。
        return $batch->receipts[0];
    }

    /**
     * 複数レースを1回のログインで処理する。
     *
     * ログイン・投票ウィンドウの生成・残高の確保はセッションに1回で済み、
     * 2レース目以降は完了画面から場選択画面へ戻って同じ手順を繰り返す。
     *
     * 途中で失敗しても例外にはせず、そこまでの Receipt を必ず返す。既に動いた
     * お金の記録を落とさないため。中止したかどうかは hasFailure() で見ること。
     * ただし1レースも投票できなかった場合だけは、単発の purchase() と揃えて例外にする。
     *
     * 締切をまたぐ・残高が届かないレースは、そのレースだけ飛ばして次へ進む。
     * 画面操作に失敗した場合は盤面が読めないので、以降のレースを中止する。
     *
     * @param non-empty-list<array{
     *     stadiumNumber: int,
     *     number: int,
     *     type: int,
     *     focuses: non-empty-array<array-key, int>,
     *     deadline?: ?DateTimeInterface
     * }>  $races  締切の早い順に並べること
     *
     * @return \BVP\Purchaser\BatchReceipt
     * @throws \BVP\Purchaser\PurchaserException
     */
    #[\Override]
    public function purchaseMany(array $races): BatchReceipt
    {
        // ブラウザを起動する前に全レースを検証し、直せない入力はここで落とす。
        $plans = $this->planRaces($races);

        $required = 0;

        foreach ($plans as $plan) {
            $required += $plan['slip']->totalAmount;
        }

        $batchLimit = $this->maxBatchAmount ?? $this->maxTotalAmount;

        if ($batchLimit !== null && $required > $batchLimit) {
            throw new PurchaserException(
                "購入金額の合計 {$required} 円が1セッションの上限 {$batchLimit} 円を超えています。"
                . '（' . count($plans) . ' レース）'
            );
        }

        // 先頭のレースにすら間に合わないなら、ブラウザを起動する前に落とす。
        $this->assertDeadline('purchase の開始', $plans[0]['deadline']);

        $started = microtime(true);

        /** @var \BVP\Purchaser\BatchReceipt */
        return $this->withSession(
            function () use ($plans, $required, $started): BatchReceipt {
                // 入金と反映待ちを全レース分まとめて先に置き切る。
                // ここを抜けた時点で、レース間で入金を挟む必要はなくなっている。
                $balance = $this->ensureBalanceInSession($required);

                return $this->placeBatch($plans, $balance, $started);
            },
            'purchase-many'
        );
    }

    /**
     * ブラウザを起動する前に、全レースの買い目・レース番号・並び順を検証する。
     *
     * @param list<array{
     *     stadiumNumber: int,
     *     number: int,
     *     type: int,
     *     focuses: non-empty-array<array-key, int>,
     *     deadline?: ?DateTimeInterface
     * }>  $races
     * @return non-empty-list<array{
     *     stadiumNumber: int,
     *     number: int,
     *     slip: BetSlip,
     *     deadline: ?DateTimeInterface
     * }>
     *
     * @throws \BVP\Purchaser\PurchaserException
     */
    private function planRaces(array $races): array
    {
        $plans = [];
        $seen = [];
        $previousDeadline = null;

        foreach ($races as $index => $race) {
            $stadiumNumber = $race['stadiumNumber'];
            $number = $race['number'];
            $type = $race['type'];

            // 個別の締切が無ければ setDeadline() の値を使う。安全側に倒すため、
            // 「指定が無い＝締切を見ない」ではなく「セッション共通の締切を見る」。
            $deadline = $race['deadline'] ?? $this->deadline;

            if ($stadiumNumber < 1 || $stadiumNumber > 24) {
                throw new PurchaserException("レース場が不正です。stadiumNumber={$stadiumNumber}（1〜24）");
            }

            if ($number < 1 || $number > 12) {
                throw new PurchaserException("レース番号が不正です。number={$number}（1〜12）");
            }

            // 同じレースの同じ賭式を2件に分けるのは、まず呼び出し側のバグ。
            // 賭式が違えば別物なので、そこまでは通す。
            $key = $stadiumNumber . '-' . $number . '-' . $type;

            if (isset($seen[$key])) {
                throw new PurchaserException(
                    "同じレース・同じ賭式が重複しています。stadiumNumber={$stadiumNumber} / "
                    . "number={$number} / type={$type}。買い目を1件にまとめてください。"
                );
            }

            $seen[$key] = true;
            $slip = BetSlip::fromFocuses($race['focuses'], $type);

            if ($this->maxTotalAmount !== null && $slip->totalAmount > $this->maxTotalAmount) {
                throw new PurchaserException(
                    "購入金額の合計 {$slip->totalAmount} 円が1レースの上限 {$this->maxTotalAmount} 円を"
                    . "超えています。stadiumNumber={$stadiumNumber} / number={$number}"
                );
            }

            // 締切の早いレースを後ろに置くと、前のレースを処理する間に締切をまたぐ。
            // 黙って並べ替えると呼び出し側の意図と食い違うので、ここで気づかせる。
            if ($deadline !== null
                && $previousDeadline !== null
                && $deadline->getTimestamp() < $previousDeadline->getTimestamp()
            ) {
                throw new PurchaserException(
                    "レースが締切の早い順に並んでいません。{$index} 番目（0 始まり）の "
                    . "{$number}R の締切が、その前のレースより早くなっています。"
                );
            }

            if ($deadline !== null) {
                $previousDeadline = $deadline;
            }

            $plans[] = [
                'stadiumNumber' => $stadiumNumber,
                'number' => $number,
                'slip' => $slip,
                'deadline' => $deadline,
            ];
        }

        if ($plans === []) {
            throw new PurchaserException('レースが空です。');
        }

        return $plans;
    }

    /**
     * 残高を $required 円まで満たすのに必要な入金額（1,000円単位に切り上げ）。
     *
     * 足りていれば 0 を返す。ブラウザに依存しないので単体テストできる。
     *
     * @param int $balance
     * @param int $required
     * @return int
     */
    public static function requiredDepositAmount(int $balance, int $required): int
    {
        $shortfall = $required - $balance;

        if ($shortfall <= 0) {
            return 0;
        }

        return intdiv($shortfall + self::DEPOSIT_UNIT - 1, self::DEPOSIT_UNIT) * self::DEPOSIT_UNIT;
    }

    /**
     * ロックを取り、ログインし、後始末までを面倒みる。
     *
     * @param callable(): mixed $callback
     * @param non-empty-string $context
     * @return mixed
     */
    private function withSession(callable $callback, string $context): mixed
    {
        $this->assertCredentials();

        $this->stepMilliseconds = [];
        $lock = new Lock($this->lockPath);
        $lock->acquire();

        try {
            $this->login();

            return $callback();
        } catch (Throwable $exception) {
            $this->captureArtifacts($context);

            if ($exception instanceof PurchaserException) {
                throw $exception;
            }

            throw new PurchaserException(
                "{$context} に失敗しました。" . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        } finally {
            $this->closeSession();
            $lock->release();
        }
    }
    /**
     * @return void
     */

    private function login(): void
    {
        $started = microtime(true);
        $driver = $this->driver();
        $driver->get(self::LOGIN_URL);

        $previousHandles = $driver->getWindowHandles();

        $this->waitVisible(WebDriverBy::id('memberNo'))->sendKeys((string) $this->subscriberNumber);
        $this->waitVisible(WebDriverBy::id('pin'))->sendKeys((string) $this->personalIdentificationNumber);
        $this->waitVisible(WebDriverBy::id('authPassword'))->sendKeys((string) $this->authenticationPassword);
        $this->click(WebDriverBy::id('loginButton'));

        // click() 直後は新ウィンドウがまだ開いていないことがあるので、増えるまで待つ。
        $expected = count($previousHandles) + 1;

        try {
            $driver->wait(self::WAIT_TIMEOUT_SECONDS, self::WAIT_INTERVAL_MILLISECONDS)->until(
                static fn(RemoteWebDriver $driver): bool => count($driver->getWindowHandles()) >= $expected
            );
        } catch (Throwable $exception) {
            throw new PurchaserException(
                'ログイン後の投票ウィンドウが開きませんでした。'
                . '加入者番号・暗証番号・認証用パスワードの誤り、メンテナンス、多重ログインを疑ってください。',
                0,
                $exception
            );
        }

        $newHandles = array_values(array_diff($driver->getWindowHandles(), $previousHandles));
        $handle = $newHandles[0] ?? null;

        if (!is_string($handle)) {
            throw new PurchaserException('ログイン後の投票ウィンドウを特定できませんでした。');
        }

        $driver->switchTo()->window($handle);
        $this->dismissNewsDialog();
        $this->recordStep('login', $started);
    }

    /**
     * お知らせダイアログは「出ていないのが正常」なので、出ていなければ短く諦める。
     *
     * WebDriverWait は NoSuchElementException を内部で握り潰して待ち続け、
     * 時間切れで TimeoutException を投げる。旧実装が NoSuchElementException を
     * catch していたのは効いておらず、ダイアログが無い日は 10 秒待った末に
     * 購入全体が中断していた。
     *
     * @return void
     */
    private function dismissNewsDialog(): void
    {
        try {
            $this->waitClickable(
                WebDriverBy::id('newsoverviewdispCloseButton'),
                self::OPTIONAL_WAIT_TIMEOUT_SECONDS
            )->click();
        } catch (Throwable $exception) {
            // 出ていなければ何もしない。
        }
    }
    /**
     * @param int $required
     * @return int
     */

    private function ensureBalanceInSession(int $required): int
    {
        $started = microtime(true);
        $balance = $this->fetchBalance();

        if ($balance >= $required) {
            $this->recordStep('ensure-balance', $started);

            return $balance;
        }

        $deposit = self::requiredDepositAmount($balance, $required);

        if ($this->maxDepositAmount !== null && $deposit > $this->maxDepositAmount) {
            throw new PurchaserException(
                "必要な入金額 {$deposit} 円が上限 {$this->maxDepositAmount} 円を超えています。"
                . "残高 {$balance} 円 / 必要 {$required} 円"
            );
        }

        $this->executeCharge($deposit);

        // 入金の反映は非同期なので、必要額を満たすまで再照会する。
        $deadline = microtime(true) + self::DEPOSIT_REFLECTION_TIMEOUT_SECONDS;
        $attempts = 0;

        while (($balance = $this->fetchBalance()) < $required) {
            $attempts++;

            if (microtime(true) >= $deadline) {
                throw new PurchaserException(
                    "入金が残高に反映されませんでした。残高 {$balance} 円 / 必要 {$required} 円 / "
                    . "入金指示 {$deposit} 円 / 再照会 {$attempts} 回"
                );
            }

            usleep(self::DEPOSIT_REFLECTION_INTERVAL_MICROSECONDS);
        }

        $this->recordStep('ensure-balance', $started);

        return $balance;
    }
    /**
     * @param int $amount
     * @return void
     */

    private function executeCharge(int $amount): void
    {
        $started = microtime(true);

        $this->click(WebDriverBy::id('gnavi01'));
        $this->click(WebDriverBy::id('charge'));

        // 入金指示は1,000円単位で入力する。
        $this->waitVisible(WebDriverBy::id('chargeInstructAmt'))
            ->sendKeys((string) intdiv($amount, self::DEPOSIT_UNIT));

        $this->waitVisible(WebDriverBy::id('chargeBetPassword'))->sendKeys((string) $this->purchasePassword);
        $this->click(WebDriverBy::id('executeCharge'));

        // ⚠ 旧実装から踏襲。id('ok') の出現を待って linkText('OK') を押している。
        // 実機で確認できていないため、この非対称はあえてそのままにしてある。
        $this->waitClickable(WebDriverBy::id('ok'));
        $this->driver()->findElement(WebDriverBy::linkText('OK'))->click();

        $this->click(WebDriverBy::id('closeChargecomp'));
        $this->recordStep('charge', $started);
    }
    /**
     * @return int
     */

    private function fetchBalance(): int
    {
        $this->click(WebDriverBy::id('gnavi02'));
        $this->click(WebDriverBy::id('balref'));

        $text = $this->waitVisible(WebDriverBy::cssSelector(self::LOCATOR_BALANCE))->getText();
        $digits = preg_replace('/[^0-9]/', '', $text);

        $this->click(WebDriverBy::id('closeBalref'));

        // 数字が1文字も取れないのは、セレクタが別の列を指すようになった等の構造変化。
        // ここで 0 を返すと「入金が反映されない」と誤診断するので、別の失敗として扱う。
        if ($digits === null || $digits === '') {
            throw new PurchaserException(
                '残高照会の解釈に失敗しました。セレクタ ' . self::LOCATOR_BALANCE . " の取得値: 「{$text}」"
            );
        }

        return (int) $digits;
    }

    /**
     * 計画したレースを順に処理する。
     *
     * @param non-empty-list<array{
     *     stadiumNumber: int,
     *     number: int,
     *     slip: BetSlip,
     *     deadline: ?DateTimeInterface
     * }>  $plans
     *
     * @param int $balance
     * @param float $startedAt
     * @return \BVP\Purchaser\BatchReceipt
     * @throws \BVP\Purchaser\PurchaserException
     */
    private function placeBatch(array $plans, int $balance, float $startedAt): BatchReceipt
    {
        // ログインと残高確保はセッション全体の費用なので、各レースの計測に重ねて持たせる。
        $sessionSteps = $this->stepMilliseconds;

        $receipts = [];
        $skipped = [];
        $failure = null;
        $totalAmount = 0;
        $onCompletionScreen = false;

        foreach ($plans as $plan) {
            $slip = $plan['slip'];
            $race = ['stadiumNumber' => $plan['stadiumNumber'], 'raceNumber' => $plan['number']];

            if ($failure !== null) {
                $skipped[] = $race + ['reason' => '先行するレースが失敗したため、以降を中止しました。'];

                continue;
            }

            try {
                $this->assertDeadline($plan['number'] . 'R の投票', $plan['deadline']);
            } catch (PurchaserException $exception) {
                // 締切をまたいだのはこのレースだけ。後続まで落とす理由はない。
                $skipped[] = $race + ['reason' => $exception->getMessage()];

                continue;
            }

            // 完了画面でもヘッダの購入限度額は読めるので、照会ダイアログを開かずに済ませる。
            if ($onCompletionScreen) {
                $balance = $this->headerBalance() ?? $balance;
            }

            // 全レース分は確保済みなので、ここで足りないのは想定外の減り方をしたとき。
            // 締切前に入金の反映を待つ余裕はないので、このレースは諦める。
            if ($balance < $slip->totalAmount) {
                $skipped[] = $race + [
                    'reason' => "残高 {$balance} 円が購入金額 {$slip->totalAmount} 円に足りません。",
                ];

                continue;
            }

            try {
                $this->stepMilliseconds = $sessionSteps;

                if ($onCompletionScreen) {
                    $this->returnToRaceSelect($plan['stadiumNumber']);
                    $onCompletionScreen = false;
                }

                $receipt = $this->placeBets(
                    $plan['stadiumNumber'],
                    $plan['number'],
                    $slip,
                    $balance,
                    $plan['deadline']
                );

                $onCompletionScreen = true;
                $receipts[] = $receipt;
                $totalAmount += $receipt->totalAmount;
                $balance -= $receipt->totalAmount;

                // 完了画面に着いても、締切済などで一部が不成立になることがある
                // （BOAT.code の acceptcode_55 等）。額が合わないなら以降は進めない。
                if ($receipt->acceptedAmount !== null && $receipt->acceptedAmount !== $slip->totalAmount) {
                    $failure = $race + [
                        'reason' => "購入成立金額 {$receipt->acceptedAmount} 円が"
                            . "投票額 {$slip->totalAmount} 円と一致しません。",
                    ];
                }
            } catch (Throwable $exception) {
                // 1レースも投票できていないなら、単発の purchase() と揃えて例外にする。
                if ($receipts === []) {
                    throw $exception;
                }

                // 買い目を登録した状態で落ちていると盤面が読めない。
                // 次のレースに持ち越さず、ここで打ち切る。
                $this->captureArtifacts("purchase-many-{$plan['stadiumNumber']}-{$plan['number']}");
                $failure = $race + ['reason' => $exception->getMessage()];
            }
        }

        $this->stepMilliseconds = $sessionSteps;

        if ($receipts === []) {
            $reasons = array_map(
                static fn(array $entry): string => $entry['reason'],
                $skipped
            );

            throw new PurchaserException('投票できたレースがありません。' . implode(' / ', $reasons));
        }

        return new BatchReceipt(
            $receipts,
            $skipped,
            $failure,
            $totalAmount,
            (int) round((microtime(true) - $startedAt) * 1000.0),
            $sessionSteps
        );
    }

    /**
     * 投票完了画面から場選択画面へ戻る。
     *
     * 「場を変更して投票する」はログイン直後と同じ場選択画面に戻るので、
     * 2レース目以降を1レース目とまったく同じ手順で処理できる。同じ場のときも
     * これを使う。「同じ場で投票する」はレース選択画面に直接入るため手順が分岐する。
     *
     * @param int $stadiumNumber
     * @return void
     * @throws \BVP\Purchaser\PurchaserException
     */
    private function returnToRaceSelect(int $stadiumNumber): void
    {
        $started = microtime(true);

        try {
            $this->click(WebDriverBy::id(self::LOCATOR_MODIFY_JYO_BET));

            // 遷移したことをここで見ておく。空振りしていると、次のレース場の
            // クリックが「要素が出ない」形で時間切れになり、原因が読めなくなる。
            $this->waitClickable(WebDriverBy::id('jyo' . sprintf('%02d', $stadiumNumber)));
        } catch (Throwable $exception) {
            throw new PurchaserException(
                '投票完了画面から場選択画面へ戻れませんでした。画面構造の変化を疑ってください。',
                0,
                $exception
            );
        }

        $this->recordStep('return-to-race-select', $started);
    }

    /**
     * ヘッダに出ている購入限度額。
     *
     * 残高照会ダイアログと同じ currentBetLimitAmount なので、レース間の確認は
     * ダイアログの開閉（4クリック）を挟まずにこれで済ませる。
     *
     * 読めなければ null を返し、呼び出し側が持っている値を使わせる。ここは
     * 残高の確定に使う経路ではないので、失敗を例外にする必要はない。
     *
     * @return ?int
     */
    private function headerBalance(): ?int
    {
        try {
            $text = $this->driver()
                ->findElement(WebDriverBy::cssSelector(self::LOCATOR_HEADER_BALANCE))
                ->getText();
        } catch (Throwable $exception) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $text);

        if ($digits === null || $digits === '') {
            return null;
        }

        return (int) $digits;
    }

    /**
     * 完了画面の契約番号と購入成立金額。
     *
     * 読めなければ null を返す。ここで例外にすると、投票が成立した直後に
     * 「失敗した」と誤って伝えることになる。
     *
     * @return array{receiptNumber: ?string, acceptedAmount: ?int}
     */
    private function fetchConfirmation(): array
    {
        try {
            $text = $this->driver()
                ->findElement(WebDriverBy::cssSelector(self::LOCATOR_CONFIRMATION))
                ->getText();
        } catch (Throwable $exception) {
            return ['receiptNumber' => null, 'acceptedAmount' => null];
        }

        // 表の組み方が変わっても効くように、行ではなくラベルを手がかりにする。
        $receiptNumber = preg_match('/契約番号\s*([0-9A-Za-z\-]+)/u', $text, $matches) === 1
            ? $matches[1]
            : null;

        $acceptedAmount = preg_match('/購入成立金額\s*([0-9,]+)\s*円/u', $text, $matches) === 1
            ? (int) str_replace(',', '', $matches[1])
            : null;

        return ['receiptNumber' => $receiptNumber, 'acceptedAmount' => $acceptedAmount];
    }
    /**
     * @param int $stadiumNumber
     * @param int $number
     * @param \BVP\Purchaser\BetSlip $slip
     * @param int $balanceBefore
     * @param ?\DateTimeInterface $deadline
     * @return \BVP\Purchaser\Receipt
     */

    private function placeBets(
        int $stadiumNumber,
        int $number,
        BetSlip $slip,
        int $balanceBefore,
        ?DateTimeInterface $deadline
    ): Receipt {
        $started = microtime(true);
        $this->assertDeadline('レースの選択', $deadline);

        $this->click(WebDriverBy::id('jyo' . sprintf('%02d', $stadiumNumber)));
        $this->click(WebDriverBy::id('selRaceNo' . sprintf('%02d', $number)));
        $this->assertRaceNumber($number);

        $this->click(WebDriverBy::id('betkati' . $slip->betType));
        $this->click(WebDriverBy::id('betway1'));
        $this->recordStep('select-race', $started);

        $registered = microtime(true);

        foreach ($slip->bets as $bet) {
            foreach ($bet['legs'] as $index => $leg) {
                $this->click(WebDriverBy::id('regbtn_' . $leg . '_' . ($index + 1)));
            }

            // 購入金額は100円単位で入力する。
            $amount = $this->waitVisible(WebDriverBy::id('amount'));
            $amount->clear();
            $amount->sendKeys((string) intdiv($bet['amount'], BetSlip::AMOUNT_UNIT));

            $this->click(WebDriverBy::id('regAmountBtn'));
        }

        $this->recordStep('register-bets', $registered);

        // 買い目の登録からここまでで数秒経っている。締切をまたいで次のレースに
        // 繰り上がっていないことを、submit の直前にもう一度確認する。
        $this->assertRaceNumber($number);
        $this->assertDeadline('投票の確定', $deadline);

        $submitted = microtime(true);
        $this->click(WebDriverBy::cssSelector('.btnSubmit'));

        // ここで入力する合計金額はサイト側の計算値と突き合わされる。
        // 買い目を取りこぼしていれば弾かれるので、この入力自体が点検になっている。
        $this->waitVisible(WebDriverBy::name('betAmount'))->sendKeys((string) $slip->totalAmount);
        $this->waitVisible(WebDriverBy::name('betPassword'))->sendKeys((string) $this->purchasePassword);
        $this->click(WebDriverBy::id('submitBet'));

        try {
            $this->click(WebDriverBy::id('ok'));
            $confirmation = $this->waitVisible(WebDriverBy::id('thanksArea'))->getText();
        } catch (Throwable $exception) {
            throw new PurchaserException(
                '投票の確定に失敗しました。合計金額の不一致、残高不足、締切超過を疑ってください。'
                . "合計 {$slip->totalAmount} 円 / {$slip->count()} 点 / 投票直前の残高 {$balanceBefore} 円",
                0,
                $exception
            );
        }

        $this->recordStep('submit', $submitted);
        $accepted = $this->fetchConfirmation();

        return new Receipt(
            $stadiumNumber,
            $number,
            $slip->betType,
            $slip->bets,
            $slip->totalAmount,
            $balanceBefore,
            new DateTimeImmutable(),
            (int) round((microtime(true) - $started) * 1000.0),
            $this->stepMilliseconds,
            $confirmation,
            $accepted['receiptNumber'],
            $accepted['acceptedAmount']
        );
    }
    /**
     * @param int $number
     * @return void
     */

    private function assertRaceNumber(int $number): void
    {
        try {
            $this->driver()->wait(self::WAIT_TIMEOUT_SECONDS, self::WAIT_INTERVAL_MILLISECONDS)->until(
                WebDriverExpectedCondition::elementTextContains(
                    WebDriverBy::xpath(self::LOCATOR_RACE_NUMBER),
                    $number . 'R'
                )
            );
        } catch (Throwable $exception) {
            throw new PurchaserException(
                "投票画面が {$number}R であることを確認できませんでした。"
                . '繰り上がり、または画面構造の変化を疑ってください。',
                0,
                $exception
            );
        }
    }

    /**
     * @param non-empty-string $step
     * @param ?\DateTimeInterface $deadline
     * @return void
     */
    private function assertDeadline(string $step, ?DateTimeInterface $deadline): void
    {
        if ($deadline === null) {
            return;
        }

        $remaining = $deadline->getTimestamp() - time();

        if ($remaining <= $this->deadlineMarginSeconds) {
            throw new PurchaserException(
                "締切まで残り {$remaining} 秒のため {$step} を中止しました。"
                . "（下限 {$this->deadlineMarginSeconds} 秒）"
            );
        }
    }
    /**
     * @return void
     */

    private function assertCredentials(): void
    {
        $missing = [];

        if (($this->subscriberNumber ?? '') === '') {
            $missing[] = '加入者番号';
        }

        if (($this->personalIdentificationNumber ?? '') === '') {
            $missing[] = '暗証番号';
        }

        if (($this->authenticationPassword ?? '') === '') {
            $missing[] = '認証用パスワード';
        }

        if (($this->purchasePassword ?? '') === '') {
            $missing[] = '投票用パスワード';
        }

        if ($missing !== []) {
            throw new PurchaserException('会員情報が設定されていません: ' . implode('、', $missing));
        }
    }

    /**
     * 一発勝負で再現できないので、失敗時の画面を必ず残す。
     *
     * @param non-empty-string $context
     * @return void
     */
    private function captureArtifacts(string $context): void
    {
        if ($this->artifactDirectory === null || $this->driver === null) {
            return;
        }

        try {
            if (!is_dir($this->artifactDirectory)) {
                mkdir($this->artifactDirectory, 0o755, true);
            }

            $prefix = $this->artifactDirectory . '/' . date('Ymd-His') . '-' . $context;
            $this->driver->takeScreenshot($prefix . '.png');
            file_put_contents($prefix . '.html', $this->driver->getPageSource());
        } catch (Throwable $exception) {
            // 証跡の保存に失敗しても、元の例外を握り潰さない。
        }
    }
    /**
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver
     */

    private function driver(): RemoteWebDriver
    {
        // コンストラクタで張ると、DI コンテナに登録しただけでブラウザが起動する。
        return $this->driver ??= RemoteWebDriver::create(
            $this->seleniumServerUrl ?? 'http://localhost:4444',
            $this->capabilities()
        );
    }
    /**
     * @return \Facebook\WebDriver\Remote\DesiredCapabilities
     */

    private function capabilities(): DesiredCapabilities
    {
        $options = new ChromeOptions();
        $options->addArguments([
            '--headless=new',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--no-sandbox',
            // 未指定だとレスポンシブ切替で要素の出方が変わりうるので固定する。
            '--window-size=1280,1024',
        ]);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        return $capabilities;
    }

    /**
     * @param ?int<1, max> $timeout
     * @param \Facebook\WebDriver\WebDriverBy $by
     * @return \Facebook\WebDriver\Remote\RemoteWebElement
     */
    private function waitVisible(WebDriverBy $by, ?int $timeout = null): RemoteWebElement
    {
        return $this->awaited(
            $timeout,
            WebDriverExpectedCondition::visibilityOfElementLocated($by)
        );
    }

    /**
     * @param ?int<1, max> $timeout
     * @param \Facebook\WebDriver\WebDriverBy $by
     * @return \Facebook\WebDriver\Remote\RemoteWebElement
     */
    private function waitClickable(WebDriverBy $by, ?int $timeout = null): RemoteWebElement
    {
        return $this->awaited(
            $timeout,
            WebDriverExpectedCondition::elementToBeClickable($by)
        );
    }

    /**
     * クリックが他要素に覆われて弾かれたら、お知らせダイアログを閉じて1度だけ撃ち直す。
     *
     * 「特別なお知らせ」は jsrender が非同期に描画するので、login() 末尾の
     * dismissNewsDialog() が空振りした直後に出てくることがある。そうなると
     * グローバルナビが <h1> に覆われ、以降のクリックが軒並み弾かれる
     * （2026-08-10 に fetchBalance() の #gnavi02 で実際に購入が落ちた）。
     *
     * どのクリックでも起こりうるので、復帰は呼び出し側ごとではなくここに置く。
     * ElementClickInterceptedException はクリックが対象に届く前に投げられるため、
     * 撃ち直しで二重に押してしまう心配はない。
     *
     * @param ?int<1, max> $timeout
     * @param \Facebook\WebDriver\WebDriverBy $by
     * @return void
     */
    private function click(WebDriverBy $by, ?int $timeout = null): void
    {
        try {
            $this->waitClickable($by, $timeout)->click();

            return;
        } catch (ElementClickInterceptedException $exception) {
            // 覆っているものを退けてから下で撃ち直す。
        }

        $this->dismissNewsDialog();

        // 要素は取り直す。ダイアログを閉じた時点で DOM が差し替わりうる。
        $this->waitClickable($by, $timeout)->click();
    }

    /**
     * 待機条件は要素そのものを返すので、直後に findElement を撃ち直さない。
     * 往復が半分になるうえ、待機と取得の間に要素が差し替わる隙も消える。
     *
     * @param ?int<1, max> $timeout
     * @param \Facebook\WebDriver\WebDriverExpectedCondition $condition
     * @return \Facebook\WebDriver\Remote\RemoteWebElement
     */
    private function awaited(?int $timeout, WebDriverExpectedCondition $condition): RemoteWebElement
    {
        $element = $this->driver()
            ->wait($timeout ?? self::WAIT_TIMEOUT_SECONDS, self::WAIT_INTERVAL_MILLISECONDS)
            ->until($condition);

        if (!$element instanceof RemoteWebElement) {
            throw new PurchaserException('要素の待機結果が不正です。');
        }

        return $element;
    }

    /**
     * @param non-empty-string $step
     * @param float $startedAt
     * @return void
     */
    private function recordStep(string $step, float $startedAt): void
    {
        $this->stepMilliseconds[$step] = (int) round((microtime(true) - $startedAt) * 1000.0);
    }
    /**
     * @return void
     */

    private function closeSession(): void
    {
        if ($this->driver === null) {
            return;
        }

        try {
            $this->driver->quit();
        } catch (Throwable $exception) {
            // 既にセッションが切れている場合は何もしない。
        } finally {
            // 次の呼び出しで作り直す。使い回すと前回の状態を引きずる。
            $this->driver = null;
        }
    }
}
