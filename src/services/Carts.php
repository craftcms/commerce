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
use craft\commerce\events\CartPurgeEvent;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\errors\SiteNotFoundException;
use craft\events\ModelEvent;
use craft\helpers\ConfigHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
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
     * @event CartPurgeEvent The event that is triggered before the carts are purged.
     *
     * This example modifies the query to only purge carts with a total price of 0.
     * You can also set the `isValid` property to `false` to prevent the carts from being purged.
     *
     * ```php
     * use craft\commerce\events\CartPurgeEvent;
     * use craft\commerce\services\Carts;
     * use yii\base\Event;
     *
     * Event::on(
     *     Carts::class,
     *     Carts::EVENT_BEFORE_PURGE_INACTIVE_CARTS,
     *     function(CartPurgeEvent $event) {
     *         $event->inactiveCartsQuery = $event->inactiveCartsQuery->andWhere(['totalPrice' => 0]);
     *     }
     * );
     * ```
     */
    public const EVENT_BEFORE_PURGE_INACTIVE_CARTS = 'beforePurgeInactiveCarts';

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
    private string|false|null $_cartNumber = null;

    /**
     * Useful for debugging how many times the cart is being requested during a request.
     *
     * @var int The number of times the cart was requested.
     */
    private int $_getCartCount = 0;

    /**
     * Initializes the cart service
     *
     * @return void
     * @throws MissingComponentException
     */
    public function init()
    {
        parent::init();

        $currentStore = Plugin::getInstance()->getStores()->getCurrentStore();

        // Complete the cart cookie config
        if (!isset($this->cartCookie['name'])) {
            $this->cartCookie['name'] = md5(sprintf('Craft.%s.%s.%s', self::class, Craft::$app->id, $currentStore->handle)) . '_commerce_cart';
        }

        $request = Craft::$app->getRequest();
        if (!$request->getIsConsoleRequest()) {
            $this->cartCookie = Craft::cookieConfig($this->cartCookie);

            $session = Craft::$app->getSession();

            // Also check pre Commerce 4.0 for a cart number in the session just in case.
            if (($session->getHasSessionId() || $session->getIsActive()) && $session->has('commerce_cart')) {
                $this->setSessionCartNumber($session->get('commerce_cart'));
                $session->remove('commerce_cart');
            }
        }
    }

    /**
     * Get the current cart for this session.
     *
     * @param bool $forceSave Force the cart.
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function getCart(bool $forceSave = false): Order
    {
        $this->loadCookie(); // @TODO Audit other public runtime entry points (e.g. forgetCart, restorePreviousCartForCurrentUser) to see if they also need loadCookie() called first

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
        $originalPaymentCurrency = $this->_cart->paymentCurrency;
        $originalUserId = $this->_cart->getCustomerId();

        // These values should always be kept up to date when a cart is retrieved from session.
        $this->_cart->lastIp = Craft::$app->getRequest()->getUserIP();
        $this->_cart->orderLanguage = Craft::$app->language;
        $this->_cart->orderSiteId = Craft::$app->getSites()->getHasCurrentSite() ? Craft::$app->getSites()->getCurrentSite()->id : Craft::$app->getSites()->getPrimarySite()->id;
        $this->_cart->paymentCurrency = $this->_getCartPaymentCurrencyIso();
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
     * Returns the existing cart for this session without creating one, setting cookies, or touching the session.
     * Returns null if no cart cookie is present or no matching cart exists.
     *
     * @since 5.7.0
     */
    public function peekCart(): ?Order
    {
        if (isset($this->_cart)) {
            return $this->_cart;
        }

        if ($this->_cartNumber === false) {
            return null;
        }

        if (!$this->_cartNumber) {
            $cookieNumber = Craft::$app->getRequest()->getCookies()->getValue($this->cartCookie['name'], false);
            if (!$cookieNumber) {
                return null;
            }
            $this->_cartNumber = $cookieNumber;
        }

        /** @var Order|null $cart */
        $cart = Order::find()
            ->number($this->_cartNumber)
            ->storeId(Plugin::getInstance()->getStores()->getCurrentStore()->id)
            ->isCompleted(false)
            ->trashed(false)
            ->one();

        if (!$cart) {
            return null;
        }

        // Don't return a cart that belongs to a credentialed user who isn't currently logged in
        // as that user. Mirrors the privacy check in _getCart(), but without forgetting the cart
        // (which would set a Set-Cookie header and defeat the purpose of this method).
        $cartCustomer = $cart->getCustomer();
        if ($cartCustomer && $cartCustomer->getIsCredentialed()) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser || $currentUser->id != $cartCustomer->id) {
                return null;
            }
        }

        $this->_cart = $cart;
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

        $currentUser = Craft::$app->getUser()->getIdentity();

        $cartCustomer = $cart?->getCustomer();

        // Did an anonymous user provide an email that belonged to a credentialed user?
        // See CartController::actionUpdate()
        $anonymousCartWithCredentialedCustomer = $cart && Craft::$app->getSession()->get('commerce:anonymousCartWithCredentialedCustomer:' . $cart->number, false);

        if ($cart && $cartCustomer && $cartCustomer->getIsCredentialed() &&
            (
                // Forget cart if they are not logged-in, and they didn't submit the credentialed users email to the cart.
                (!$currentUser && !$anonymousCartWithCredentialedCustomer)
                ||
                // Forget cart if the logged-in user is not the same as the cart customer.
                ($currentUser && $currentUser->id != $cartCustomer->id)
            )
        ) {
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
        // Force a new cart number to be generated when next requested.
        $this->_cartNumber = false;
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
        return bin2hex(random_bytes(16));
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
        if ($this->_cartNumber === false) {
            return false;
        }

        if ($this->_cartNumber === null) {
            $request = Craft::$app->getRequest();
            $requestCookies = $request->getCookies();

            return $requestCookies->getValue($this->cartCookie['name'], false) !== false;
        }

        return true;
    }

    /**
     * Get the session cart number or generates one if none exists.
     *
     */
    protected function getSessionCartNumber(): string
    {
        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
            $request = Craft::$app->getRequest();
            $requestCookies = $request->getCookies();

            // Only try to retrieve the cart number from the cookie if `_cartNumber` is `null`.
            if ($this->_cartNumber === null && $cookieNumber = $requestCookies->getValue($this->cartCookie['name'])) {
                $this->_cartNumber = $cookieNumber;
            }
        }

        // A `null` or `false` value means we need to generate a new cart number.
        if ($this->_cartNumber === null || $this->_cartNumber === false) {
            $this->_cartNumber = $this->generateCartNumber();
        }

        /// Just in case the current cart is not the one in session, clear the cached cart.
        if ($this->_cart && $this->_cart->number !== $this->_cartNumber) {
            $this->_cart = null;
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
     * Returns a URL to load a cart with a secure token.
     *
     * @param Order $cart The cart to generate the load URL for
     * @return string The URL with secure token
     * @since 5.7.0
     */
    public function getLoadCartUrl(Order $cart): string
    {
        $linkExpiry = Plugin::getInstance()->getSettings()->cartLoadUrlExpiry;
        $expiryDate = DateTimeHelper::currentUTCDateTime()->add(DateTimeHelper::secondsToInterval($linkExpiry));

        $token = Craft::$app->getTokens()->createToken([
            'commerce/cart/load-cart',
            ['cartNumber' => $cart->number],
        ], expiryDate: $expiryDate);

        $request = Craft::$app->getRequest();
        $isCpRequest = $request->getIsCpRequest();
        $request->setIsCpRequest(false);
        $url = UrlHelper::actionUrl('commerce/cart/load-cart', [
            'number' => $cart->number,
            'code' => $token,
        ]);
        $request->setIsCpRequest($isCpRequest);

        return $url;
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
        $currentStoreId = Plugin::getInstance()->getStores()->getCurrentStore()->id;

        if (!$currentUser) {
            return;
        }

        // If the current cart is empty see if the logged-in user has a previous cart
        // Get any cart that is not empty, is not trashed or complete, and belongings to the user
        /** @var Order|null $previousCartsWithLineItems */
        $previousCartsWithLineItems = Order::find()
            ->customer($currentUser)
            ->isCompleted(false)
            ->hasLineItems()
            ->trashed(false)
            ->storeId($currentStoreId)
            ->one();

        /** @var Order|null $anyPreviousCart */
        $anyPreviousCart = Order::find()
            ->customer($currentUser)
            ->isCompleted(false)
            ->trashed(false)
            ->storeId($currentStoreId)
            ->one();

        /** @var Order|null $currentCartInSession */
        $currentCartInSession = Order::find()
            ->number($this->getSessionCartNumber())
            ->isCompleted(false)
            ->hasLineItems()
            ->trashed(false)
            ->storeId($currentStoreId)
            ->one();

        /**
         * Cart restoring preference order:
         * 1. Give the cart in session to the current customer if they are logging in and there are items in the cart
         * 2. Restore a previous cart belonging to the customer that has line items
         * 3. Restore any other previous cart for the customer
         */
        if ($currentCartInSession) {
            // Give the cart to the current customer if they are logging in and there are items in the cart
            // Call get cart as this will switch the user and save it if needed
            $this->getCart();
        } elseif ($previousCartsWithLineItems) {
            // Restore previous cart that has line items
            $this->_cart = $previousCartsWithLineItems;
            $this->setSessionCartNumber($previousCartsWithLineItems->number);
        } elseif ($anyPreviousCart) {
            // Finally try to restore any other previous cart for the customer
            $this->_cart = $anyPreviousCart;
            $this->setSessionCartNumber($anyPreviousCart->number);
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
        };

        $configInterval = ConfigHelper::durationInSeconds(Plugin::getInstance()->getSettings()->purgeInactiveCartsDuration);
        $edge = new DateTime();
        $interval = DateTimeHelper::secondsToInterval($configInterval);
        $edge->sub($interval);

        $cartIdsQuery = (new Query())
            ->select(['orders.id'])
            ->where(['not', ['isCompleted' => true]])
            ->andWhere('[[orders.dateUpdated]] <= :edge', ['edge' => Db::prepareDateForDb($edge)])
            ->from(['orders' => Table::ORDERS]);

        $event = new CartPurgeEvent([
            'inactiveCartsQuery' => $cartIdsQuery,
        ]);

        if ($this->hasEventHandlers(self::EVENT_BEFORE_PURGE_INACTIVE_CARTS)) {
            $this->trigger(self::EVENT_BEFORE_PURGE_INACTIVE_CARTS, $event);
        }

        if (!$event->isValid) {
            return 0;
        }

        // Taken from craft\services\Elements::deleteElement(); Using the method directly
        // takes too many resources since it retrieves the order before deleting it.
        // Delete the elements table rows, which will cascade across all other InnoDB tables
        Craft::$app->getDb()->createCommand()
            ->delete('{{%elements}}', ['id' => $event->inactiveCartsQuery])
            ->execute();

        // The searchindex table is probably MyISAM, though
        Craft::$app->getDb()->createCommand()
            ->delete('{{%searchindex}}', ['elementId' => $event->inactiveCartsQuery])
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

        // Don't restore from cookie if the cart was explicitly forgotten this request.
        if ($this->_cartNumber === false) {
            return;
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
     * @todo in Commerce 6.0, replace the COMMERCE_PAYMENT_CURRENCY constant with a proper per-store config setting and surface validation errors instead of throwing InvalidConfigException
     */
    private function _getCartPaymentCurrencyIso(): string
    {
        if ($this->_cart) {
            // Is the payment currency locked to the constant
            if (defined('COMMERCE_PAYMENT_CURRENCY')) {
                $paymentCurrencies = Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies($this->_cart->storeId);
                // if not in array
                if (!$paymentCurrencies->contains('iso', '==', COMMERCE_PAYMENT_CURRENCY)) {
                    throw new InvalidConfigException('The COMMERCE_PAYMENT_CURRENCY constant is not set to a valid payment currency.');
                }

                $this->_cart->paymentCurrency = COMMERCE_PAYMENT_CURRENCY;
            }

            return $this->_cart->paymentCurrency;
        }

        return Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
    }

    /**
     * @param ModelEvent $event
     * @return void
     * @throws MissingComponentException
     * @throws Throwable
     */
    public function afterSaveUserHandler(ModelEvent $event): void
    {
        $segments = Craft::$app->getRequest()->getActionSegments();
        $userSaveSegments = ['users', 'save-user'];
        $isUserSaveAction = $segments == $userSaveSegments;

        // we have a cart number, currently anon, and the current action being executed is user save
        if (!Craft::$app->getUser()->getIdentity() &&
            !Craft::$app->getRequest()->getIsCpRequest() &&
            $isUserSaveAction
        ) {
            $currentCartNumber = $this->getSessionCartNumber();
            // Set the session flag to preserve the cart for this user
            Craft::$app->getSession()->set('commerce:anonymousCartWithCredentialedCustomer:' . $currentCartNumber, true);
        }
    }
}
