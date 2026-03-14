<?php

declare(strict_types=1);

namespace BVP\Purchaser;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use RuntimeException;

/**
 * @author shimomo
 */
final class PurchaserCore implements PurchaserCoreInterface
{
    /**
     * @psalm-var \Facebook\WebDriver\Remote\RemoteWebDriver
     *
     * @var \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    private RemoteWebDriver $driver;

    /**
     * @psalm-param ?non-empty-string $seleniumServerUrl
     * @psalm-param ?int<1000, max> $depositAmount
     * @psalm-param ?non-empty-string $subscriberNumber
     * @psalm-param ?non-empty-string $personalIdentificationNumber
     * @psalm-param ?non-empty-string $authenticationPassword
     * @psalm-param ?non-empty-string $purchasePassword
     * @psalm-return void
     *
     * @param ?string $seleniumServerUrl
     * @param ?int<1000, max> $depositAmount
     * @param ?string $subscriberNumber
     * @param ?string $personalIdentificationNumber
     * @param ?string $authenticationPassword
     * @param ?string $purchasePassword
     * @return void
     */
    public function __construct(
        private ?string $seleniumServerUrl = null,
        private ?int $depositAmount = null,
        private ?string $subscriberNumber = null,
        private ?string $personalIdentificationNumber = null,
        private ?string $authenticationPassword = null,
        private ?string $purchasePassword = null
    ) {
        $options = new ChromeOptions();
        $options->addArguments(['--headless']);
        $capabilities = new DesiredCapabilities();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
        $this->driver = RemoteWebDriver::create($seleniumServerUrl ?? 'http://localhost:4444', $capabilities);
    }

    /**
     * @psalm-return void
     *
     * @return void
     */
    public function __destruct()
    {
        $this->driver->quit();
    }

    /**
     * @psalm-param int<1000, max> $depositAmount
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param int $depositAmount
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setDepositAmount(int $depositAmount): PurchaserCore
    {
        $this->depositAmount = $depositAmount;

        return $this;
    }

    /**
     * @psalm-param non-empty-string $subscriberNumber
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param string $subscriberNumber
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setSubscriberNumber(string $subscriberNumber): PurchaserCore
    {
        $this->subscriberNumber = $subscriberNumber;

        return $this;
    }

    /**
     * @psalm-param non-empty-string $personalIdentificationNumber
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param string $personalIdentificationNumber
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setPersonalIdentificationNumber(string $personalIdentificationNumber): PurchaserCore
    {
        $this->personalIdentificationNumber = $personalIdentificationNumber;

        return $this;
    }

    /**
     * @psalm-param non-empty-string $authenticationPassword
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param string $authenticationPassword
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setAuthenticationPassword(string $authenticationPassword): PurchaserCore
    {
        $this->authenticationPassword = $authenticationPassword;

        return $this;
    }

    /**
     * @psalm-param non-empty-string $purchasePassword
     * @psalm-return \BVP\Purchaser\PurchaserCore
     *
     * @param string $purchasePassword
     * @return \BVP\Purchaser\PurchaserCore
     */
    #[\Override]
    public function setPurchasePassword(string $purchasePassword): PurchaserCore
    {
        $this->purchasePassword = $purchasePassword;

        return $this;
    }

    /**
     * @psalm-param int<1, 24> $stadiumNumber
     * @psalm-param int<1, 12> $number
     * @psalm-param int<1, 7> $type
     * @psalm-param non-empty-array<non-empty-string, int<100, max>> $focuses
     * @psalm-return void
     *
     * @param int $stadiumNumber
     * @param int $number
     * @param int $type
     * @param array $focuses
     * @return void
     */
    #[\Override]
    public function purchase(int $stadiumNumber, int $number, int $type, array $focuses): void
    {
        $this->driver->get('https://ib.mbrace.or.jp/');

        /** @psalm-var non-empty-list<non-empty-string> */
        $previousHandles = $this->driver->getWindowHandles();

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('memberNo')));
        $this->driver->findElement(WebDriverBy::id('memberNo'))->sendKeys($this->subscriberNumber);

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('pin')));
        $this->driver->findElement(WebDriverBy::id('pin'))->sendKeys($this->personalIdentificationNumber);

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('authPassword')));
        $this->driver->findElement(WebDriverBy::id('authPassword'))->sendKeys($this->authenticationPassword);

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('loginButton')));
        $this->driver->findElement(WebDriverBy::id('loginButton'))->click();

        /** @psalm-var non-empty-list<non-empty-string> */
        $handles = $this->driver->getWindowHandles();
        $newHandle = array_diff($handles, $previousHandles);
        $handle = reset($newHandle);
        if (!is_string($handle)) {
            return;
        }

        $this->driver->switchTo()->window($handle);

        try {
            $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('newsoverviewdispCloseButton')));
            $this->driver->findElement(WebDriverBy::id('newsoverviewdispCloseButton'))->click();
        } catch (NoSuchElementException $exception) {
            // Nothing
        }

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('gnavi01')));
        $this->driver->findElement(WebDriverBy::id('gnavi01'))->click();

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('charge')));
        $this->driver->findElement(WebDriverBy::id('charge'))->click();

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('chargeInstructAmt')));
        $this->driver->findElement(WebDriverBy::id('chargeInstructAmt'))->sendKeys(($this->depositAmount ?? 1000) / 1000);

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('chargeBetPassword')));
        $this->driver->findElement(WebDriverBy::id('chargeBetPassword'))->sendKeys($this->purchasePassword);

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('executeCharge')));
        $this->driver->findElement(WebDriverBy::id('executeCharge'))->click();

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('ok')));
        $this->driver->findElement(WebDriverBy::linkText('OK'))->click();

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('closeChargecomp')));
        $this->driver->findElement(WebDriverBy::id('closeChargecomp'))->click();

        for ($depositCounter = 1; $depositCounter <= 10; ++$depositCounter) {
            $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('gnavi02')));
            $this->driver->findElement(WebDriverBy::id('gnavi02'))->click();

            $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('balref')));
            $this->driver->findElement(WebDriverBy::id('balref'))->click();

            $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::cssSelector('.gray > .col3')));
            $depositAmount = (int) preg_replace('/[^0-9]/', '', $this->driver->findElement(WebDriverBy::cssSelector('.gray > .col3'))->getText());

            $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('closeBalref')));
            $this->driver->findElement(WebDriverBy::id('closeBalref'))->click();

            if ($depositAmount >= $this->depositAmount) {
                break;
            }

            if ($depositCounter >= 10) {
                throw new RuntimeException('入金に失敗しました。');
            }
        }

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('jyo' . sprintf('%02d', $stadiumNumber))));
        $this->driver->findElement(WebDriverBy::id('jyo' . sprintf('%02d', $stadiumNumber)))->click();

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('selRaceNo' . sprintf('%02d', $number))));
        $this->driver->findElement(WebDriverBy::id('selRaceNo' . sprintf('%02d', $number)))->click();

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementTextContains(WebDriverBy::xpath('descendant-or-self::body/div[1]/main/div/div[1]/section[2]/div[2]/dl/dt/strong'), $number . 'R'));

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('betkati' . $type)));
        $this->driver->findElement(WebDriverBy::id('betkati' . $type))->click();

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('betway1')));
        $this->driver->findElement(WebDriverBy::id('betway1'))->click();

        $totalAmount = 0;

        foreach ($focuses as $focus => $amount) {
            $focusParts = preg_split('/[-=]/', $focus);

            if (!$focusParts) {
                continue;
            }

            foreach ($focusParts as $index => $focusPart) {
                $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('regbtn_' . $focusPart . '_' . $index + 1)));
                $this->driver->findElement(WebDriverBy::id('regbtn_' . $focusPart . '_' . $index + 1))->click();
            }

            $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('amount')));
            $this->driver->findElement(WebDriverBy::id('amount'))->clear();
            $this->driver->findElement(WebDriverBy::id('amount'))->sendKeys($amount / 100);

            $totalAmount += $amount;

            $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('regAmountBtn')));
            $this->driver->findElement(WebDriverBy::id('regAmountBtn'))->click();
        }

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('.btnSubmit')));
        $this->driver->findElement(WebDriverBy::cssSelector('.btnSubmit'))->click();

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name('betAmount')));
        $this->driver->findElement(WebDriverBy::name('betAmount'))->sendKeys($totalAmount);

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name('betPassword')));
        $this->driver->findElement(WebDriverBy::name('betPassword'))->sendKeys($this->purchasePassword);

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('submitBet')));
        $this->driver->findElement(WebDriverBy::id('submitBet'))->click();

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::id('ok')));
        $this->driver->findElement(WebDriverBy::id('ok'))->click();

        $this->driver->wait(10, 500)->until(WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::id('thanksArea')));

        foreach ($handles as $handle) {
            $this->driver->switchTo()->window($handle);
            $this->driver->close();
        }
    }
}
