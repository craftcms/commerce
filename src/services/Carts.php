<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\errors\SiteNotFoundException;
use craft\helpers\ConfigHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use DateTime;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\Cookie;

/**
 * Cart service. This manages the cart currently in the session, this service should mainly be used by web controller actions.
 *
 * @property-read string|mixed $sessionCartNumber
 * @property bool $hasSessionCartNumber
 * @property string $activeCartEdgeDuration
 * @property Order $cart
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Carts extends Component
{
    /**
     * @var array The configuration of the cart cookie.
     * @since 4.0.0
     * @see setSessionCartNumber()
     */
    public array $cartCookie = [];

    /**
     * @var int The expiration duration of the cart cookie, in seconds. (Defaults to one year.)
     * @since 4.0.0
     * @see setSessionCartNumber()
     */
    public int $cartCookieDuration = 31536000;

    /**
     * @var Order|null
     */
    private ?Order $_cart = null;

    /**
     * @var string|null The current cart number
     */
    private ?string $_cartNumber = null;

    /**
     * Useful for debugging how many times the cart is being requested during a request.
     *
     * @var int The number of times the cart was requested.
     */
    private int $_getCartCount = 0;

    /**
     * Get the current cart for this session.
     *
     * @param bool $forceSave Force the cart to save when requesting it.
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function getCart(bool $forceSave = false): Order
    {
        $this->loadCookie(); // TODO: need to see if this should be added to other runtime methods too

        $this->_getCartCount++; //useful when debugging
        $currentUser = Craft::$app->getUser()->getIdentity();

        // If there is no cart set for this request, and we can't get a cart from session, create one.
        if (!isset($this->_cart) && !$this->_cart = $this->_getCart()) {
            $cartAttributes = [
                'number' => $this->getSessionCartNumber(),
                'orderSiteId' => Craft::$app->getSites()->getCurrentSite()->id,
                'storeId' => Plugin::getInstance()->getStores()->getCurrentStore()->id,
            ];
            if ($currentUser) {
                $cartAttributes['customer'] = $currentUser; // Will ensure the email is also set
            }

            $this->_cart = Craft::createObject([
                'class' => Order::class,
                'attributes' => $cartAttributes,
            ]);
        } elseif ($this->_cart->orderSiteId != Craft::$app->getSites()->getCurrentSite()->id) {
            $this->_cart->orderSiteId = Craft::$app->getSites()->getCurrentSite()->id;
            $forceSave = true;
        }
        if ($this->_cart->autoSetShippingMethod() || $this->_cart->autoSetPaymentSource()) {
            $forceSave = true;
        }

        $autoSetAddresses = false;
        // We only want to call autoSetAddresses() if we have a authed cart customer
        if ($currentUser && $currentUser->id == $this->_cart->customerId) {
            $autoSetAddresses = $this->_cart->autoSetAddresses();
        }
        $autoSetShippingMethod = $this->_cart->autoSetShippingMethod();
        $autoSetPaymentSource = $this->_cart->autoSetPaymentSource();
        if ($autoSetAddresses || $autoSetShippingMethod || $autoSetPaymentSource) {
            $forceSave = true;
        }

        // Ensure the session knows what the current cart is.
        $this->setSessionCartNumber($this->_cart->number);

        // Track the things that might change on this cart
        $originalIp = $this->_cart->lastIp;
        $originalOrderLanguage = $this->_cart->orderLanguage;
        $originalSiteId = $this->_cart->orderSiteId;
        $originalPaymentCurrency = $this->_getCartPaymentCurrencyIso();
        $originalUserId = $this->_cart->getCustomerId();

        // These values should always be kept up to date when a cart is retrieved from session.
        $this->_cart->lastIp = Craft::$app->getRequest()->getUserIP();
        $this->_cart->orderLanguage = Craft::$app->language;
        $this->_cart->orderSiteId = Craft::$app->getSites()->getHasCurrentSite() ? Craft::$app->getSites()->getCurrentSite()->id : Craft::$app->getSites()->getPrimarySite()->id;
        $this->_cart->paymentCurrency = $this->_cart->paymentCurrency ?: $this->_cart->getStore()->getSettings()->getCurrency();
        $this->_cart->origin = Order::ORIGIN_WEB;

        // Switch the cart customer if needed
        if ($currentUser && ($this->_cart->getCustomer() === null || ($currentUser->email && $currentUser->email !== $this->_cart->getEmail()))) {
            $this->_cart->setCustomer($currentUser);
        }

        $hasIpChanged = $originalIp != $this->_cart->lastIp;
        $hasOrderLanguageChanged = $originalOrderLanguage != $this->_cart->orderLanguage;
        $hasOrderSiteIdChanged = $originalSiteId != $this->_cart->orderSiteId;
        $hasPaymentCurrencyChanged = $originalPaymentCurrency != $this->_cart->paymentCurrency;
        $hasUserChanged = $originalUserId != $this->_cart->getCustomerId();

        $hasSomethingChangedOnCart = ($hasIpChanged || $hasOrderLanguageChanged || $hasUserChanged || $hasPaymentCurrencyChanged || $hasOrderSiteIdChanged);

        // If the cart has already been saved (has an ID), then only save if something else changed.
        if (($this->_cart->id && $hasSomethingChangedOnCart) || $forceSave) {
            Craft::$app->getElements()->saveElement($this->_cart, false);
        }

        return $this->_cart;
    }

    /**
     * Get the current cart for this session.
     */
    private function _getCart(): ?Order
    {
        $number = $this->getSessionCartNumber();
        /** @var Order|null $cart */
        $cart = Order::find()
            ->withLineItems()
            ->withAdjustments()
            ->number($number)
            ->storeId(Plugin::getInstance()->getStores()->getCurrentStore()->id)
            ->trashed(null)
            ->status(null)
            ->one();

        // If the cart is already completed or trashed, forget the cart and start again.
        if ($cart && ($cart->isCompleted || $cart->trashed)) {
            $this->forgetCart();
            return null;
        }

        return $cart;
    }

    /**
     * Forgets the cart in the current session.
     */
    public function forgetCart(): void
    {
        $this->_cart = null;
        $this->_cartNumber = null;
        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
            $cookie = Craft::createObject(array_merge($this->cartCookie, [
                'class' => Cookie::class,
            ]));

            Craft::$app->getResponse()->getCookies()->remove($cookie, true);
        }
    }

    /**
     * Generates a new random cart number and returns it.
     *
     * @since 2.0
     */
    public function generateCartNumber(): string
    {
        return md5(uniqid((string)mt_rand(), true));
    }

    /**
     * Calculates the date of the active cart duration edge.
     *
     * @throws \Exception
     * @since 2.2
     */
    public function getActiveCartEdgeDuration(): string
    {
        $edge = new DateTime();
        $activeCartDuration = ConfigHelper::durationInSeconds(Plugin::getInstance()->getSettings()->activeCartDuration);
        $interval = DateTimeHelper::secondsToInterval($activeCartDuration);
        $edge->sub($interval);
        return $edge->format(DateTime::ATOM);
    }

    /**
     * @since 3.1
     * @deprecated in 4.0.0. The cookie name is available via [[$cartCookie]] `['name']`.
     */
    public function getCartName(): string
    {
        return $this->cartCookie['name'];
    }

    /**
     * Returns whether there is a cart number in the session.
     *
     * @throws MissingComponentException
     * @since 2.1.11
     */
    public function getHasSessionCartNumber(): bool
    {
        return ($this->_cartNumber !== null);
    }

    /**
     * Get the session cart number or generates one if none exists.
     *
     */
    protected function getSessionCartNumber(): string
    {
        if ($this->_cartNumber === null) {
            $this->_cartNumber = $this->generateCartNumber();
        }

        return $this->_cartNumber;
    }

    /**
     * Set the session cart number.
     */
    public function setSessionCartNumber(string $cartNumber): void
    {
        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->_cartNumber = $cartNumber;
            $cookie = Craft::createObject(array_merge($this->cartCookie, [
                'class' => Cookie::class,
                'value' => $cartNumber,
                'expire' => time() + $this->cartCookieDuration,
            ]));
            Craft::$app->getResponse()->getCookies()->add($cookie);
        }
    }

    /**
     * Restores previous cart for the current user if their current cart is empty.
     * Ideally this is only used when a user logs in.
     *
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws MissingComponentException
     * @throws Throwable
     */
    public function restorePreviousCartForCurrentUser(): void
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        $cart = $this->getCart();

        // If the current cart is empty see if the logged-in user has a previous cart
        // Get any cart that is not empty, is not trashed or complete, and belongings to the user
        /** @var Order|null $previousCart */
        $previousCart = Order::find()
            ->customer($currentUser)
            ->isCompleted(false)
            ->hasLineItems()
            ->trashed(false)
            ->one();

        if ($currentUser &&
            $cart->getIsEmpty() &&
            $previousCart
        ) {
            $this->_cart = $previousCart;
            $this->setSessionCartNumber($previousCart->number);
        }
    }

    /**
     * Removes all carts that are incomplete and older than the config setting.
     *
     * @return int The number of carts purged from the database
     * @throws \Exception
     * @throws Throwable
     */
    public function purgeIncompleteCarts(): int
    {
        if (!Plugin::getInstance()->getSettings()->purgeInactiveCarts) {
            return 0;
        }

        $configInterval = ConfigHelper::durationInSeconds(Plugin::getInstance()->getSettings()->purgeInactiveCartsDuration);
        $edge = new DateTime();
        $interval = DateTimeHelper::secondsToInterval($configInterval);
        $edge->sub($interval);

        $cartIdsQuery = (new Query())
            ->select(['orders.id'])
            ->where(['not', ['isCompleted' => true]])
            ->andWhere('[[orders.dateUpdated]] <= :edge', ['edge' => Db::prepareDateForDb($edge)])
            ->from(['orders' => Table::ORDERS]);

        // Taken from craft\services\Elements::deleteElement(); Using the method directly
        // takes too many resources since it retrieves the order before deleting it.
        // Delete the elements table rows, which will cascade across all other InnoDB tables
        Craft::$app->getDb()->createCommand()
            ->delete('{{%elements}}', ['id' => $cartIdsQuery])
            ->execute();

        // The searchindex table is probably MyISAM, though
        Craft::$app->getDb()->createCommand()
            ->delete('{{%searchindex}}', ['elementId' => $cartIdsQuery])
            ->execute();

        return $cartIdsQuery->count();
    }

    /**
     * @return void
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    protected function loadCookie(): void
    {
        $currentStore = Plugin::getInstance()->getStores()->getCurrentStore();

        // Complete the cart cookie config
        if (!isset($this->cartCookie['name'])) {
            $this->cartCookie['name'] = md5(sprintf('Craft.%s.%s.%s', self::class, Craft::$app->id, $currentStore->handle)) . '_commerce_cart';
        }

        $request = Craft::$app->getRequest();
        if (!$request->getIsConsoleRequest()) {
            $this->cartCookie = Craft::cookieConfig($this->cartCookie);

            $requestCookies = $request->getCookies();

            // If we have a cart cookie, assign it to the cart number.
            if ($requestCookies->has($this->cartCookie['name'])) {
                $this->setSessionCartNumber($requestCookies->getValue($this->cartCookie['name']));
            }
        }
    }

    /**
     * Gets the current payment currency ISO code
     */
    private function _getCartPaymentCurrencyIso(): string
    {
        if ($this->_cart) {
            // Is the payment currency locked to the constant
            if (defined('COMMERCE_PAYMENT_CURRENCY')) {
                $paymentCurrencies = Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies($this->_cart->storeId);
                // if not in array
                if ($paymentCurrencies->contains()) {
                    throw new InvalidConfigException('The COMMERCE_PAYMENT_CURRENCY constant is not set to a valid payment currency.');
                }
            }

            return $this->_cart->paymentCurrency;
        }
    }
}
