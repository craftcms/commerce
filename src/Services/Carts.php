<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\events\ModelEvent;
use craft\helpers\Db as CraftDb;
use CraftCms\Cms\Support\Config;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Events\CartPurgeEvent;
use DateTime;
use Illuminate\Container\Attributes\Singleton;
use Throwable;
use yii\web\Cookie;

#[Singleton]
class Carts
{
    public const string EVENT_BEFORE_PURGE_INACTIVE_CARTS = 'beforePurgeInactiveCarts';

    /**
     * @var array The configuration of the cart cookie.
     * @see setSessionCartNumber()
     */
    public array $cartCookie = [];

    /**
     * @var int The expiration duration of the cart cookie, in seconds. (Defaults to one year.)
     * @see setSessionCartNumber()
     */
    public int $cartCookieDuration = 31536000;

    private ?Order $cart = null;

    /**
     * @var string|false|null The current cart number
     */
    private string|false|null $cartNumber = null;

    public function __construct()
    {
        $currentStore = Plugin::getInstance()->getStores()->getCurrentStore();

        // Complete the cart cookie config
        if (!isset($this->cartCookie['name'])) {
            $this->cartCookie['name'] = md5(sprintf('Craft.%s.%s.%s', self::class, \Craft::$app->id, $currentStore->handle)) . '_commerce_cart';
        }

        $request = \Craft::$app->getRequest();
        if (!$request->getIsConsoleRequest()) {
            $this->cartCookie = \Craft::cookieConfig($this->cartCookie);

            $session = \Craft::$app->getSession();

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
     */
    public function getCart(bool $forceSave = false): Order
    {
        $this->loadCookie(); // @TODO Audit other public runtime entry points (e.g. forgetCart, restorePreviousCartForCurrentUser) to see if they also need loadCookie() called first

        $currentUser = \Craft::$app->getUser()->getIdentity();

        // If there is no cart set for this request, and we can't get a cart from session, create one.
        if (!isset($this->cart) && !$this->cart = $this->getCartFromSession()) {
            $cartAttributes = [
                'number' => $this->getSessionCartNumber(),
                'orderSiteId' => \Craft::$app->getSites()->getCurrentSite()->id,
                'storeId' => Plugin::getInstance()->getStores()->getCurrentStore()->id,
            ];

            if ($currentUser) {
                $cartAttributes['customer'] = $currentUser; // Will ensure the email is also set
            }

            $this->cart = \Craft::createObject([
                'class' => Order::class,
                'attributes' => $cartAttributes,
            ]);
        } elseif ($this->cart->orderSiteId != \Craft::$app->getSites()->getCurrentSite()->id) {
            $this->cart->orderSiteId = \Craft::$app->getSites()->getCurrentSite()->id;
            $forceSave = true;
        }

        // Just in case the cart go put into a non all recalculation mode
        if ($this->cart->getRecalculationMode() !== Order::RECALCULATION_MODE_ALL) {
            $this->cart->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
            $forceSave = true;
        }

        $autoSetAddresses = false;
        // We only want to call autoSetAddresses() if we have a authed cart customer
        if ($currentUser && $currentUser->id == $this->cart->customerId) {
            $autoSetAddresses = $this->cart->autoSetAddresses();
        }
        $autoSetShippingMethod = $this->cart->autoSetShippingMethod();
        $autoSetPaymentSource = $this->cart->autoSetPaymentSource();
        if ($autoSetAddresses || $autoSetShippingMethod || $autoSetPaymentSource) {
            $forceSave = true;
        }

        // Ensure the session knows what the current cart is.
        $this->setSessionCartNumber($this->cart->number);

        // Track the things that might change on this cart
        $originalIp = $this->cart->lastIp;
        $originalOrderLanguage = $this->cart->orderLanguage;
        $originalSiteId = $this->cart->orderSiteId;
        $originalPaymentCurrency = $this->cart->paymentCurrency;
        $originalUserId = $this->cart->getCustomerId();

        // These values should always be kept up to date when a cart is retrieved from session.
        $this->cart->lastIp = \Craft::$app->getRequest()->getUserIP();
        $this->cart->orderLanguage = \Craft::$app->language;
        $this->cart->orderSiteId = \Craft::$app->getSites()->getHasCurrentSite() ? \Craft::$app->getSites()->getCurrentSite()->id : \Craft::$app->getSites()->getPrimarySite()->id;
        $this->cart->paymentCurrency = $this->getCartPaymentCurrencyIso();
        $this->cart->origin = Order::ORIGIN_WEB;

        // Switch the cart customer if needed
        if ($currentUser && ($this->cart->getCustomer() === null || ($currentUser->email && $currentUser->email !== $this->cart->getEmail()))) {
            $this->cart->setCustomer($currentUser);
        }

        $hasIpChanged = $originalIp != $this->cart->lastIp;
        $hasOrderLanguageChanged = $originalOrderLanguage != $this->cart->orderLanguage;
        $hasOrderSiteIdChanged = $originalSiteId != $this->cart->orderSiteId;
        $hasPaymentCurrencyChanged = $originalPaymentCurrency != $this->cart->paymentCurrency;
        $hasUserChanged = $originalUserId != $this->cart->getCustomerId();

        $hasSomethingChangedOnCart = ($hasIpChanged || $hasOrderLanguageChanged || $hasUserChanged || $hasPaymentCurrencyChanged || $hasOrderSiteIdChanged);

        // If the cart has already been saved (has an ID), then only save if something else changed.
        if (($this->cart->id && $hasSomethingChangedOnCart) || $forceSave) {
            \Craft::$app->getElements()->saveElement($this->cart, false);
        }

        return $this->cart;
    }

    /**
     * Returns the existing cart for this session without creating one, setting cookies, or touching the session.
     * Returns null if no cart cookie is present or no matching cart exists.
     */
    public function peekCart(): ?Order
    {
        if (isset($this->cart)) {
            return $this->cart;
        }

        if ($this->cartNumber === false) {
            return null;
        }

        if (!$this->cartNumber) {
            $cookieNumber = \Craft::$app->getRequest()->getCookies()->getValue($this->cartCookie['name'], false);
            if (!$cookieNumber) {
                return null;
            }
            $this->cartNumber = $cookieNumber;
        }

        /** @var Order|null $cart */
        $cart = Order::find()
            ->number($this->cartNumber)
            ->storeId(Plugin::getInstance()->getStores()->getCurrentStore()->id)
            ->isCompleted(false)
            ->trashed(false)
            ->one();

        if (!$cart) {
            return null;
        }

        // Don't return a cart that belongs to a credentialed user who isn't currently logged in
        // as that user, unless this session has been authorized to use it (e.g. loaded via a valid
        // load-cart token). Mirrors the privacy check in getCartFromSession(), but without forgetting
        // the cart (which would set a Set-Cookie header and defeat the purpose of this method).
        $cartCustomer = $cart->getCustomer();
        if ($cartCustomer && $cartCustomer->getIsCredentialed()) {
            $authorizedForCredentialedCart = \Craft::$app->getSession()->get('commerce:anonymousCartWithCredentialedCustomer:' . $cart->number, false);
            if (!$authorizedForCredentialedCart) {
                $currentUser = \Craft::$app->getUser()->getIdentity();
                if (!$currentUser || $currentUser->id != $cartCustomer->id) {
                    return null;
                }
            }
        }

        $this->cart = $cart;
        return $this->cart;
    }

    /**
     * Get the current cart for this session.
     */
    private function getCartFromSession(): ?Order
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

        $currentUser = \Craft::$app->getUser()->getIdentity();

        $cartCustomer = $cart?->getCustomer();

        // Is this session authorized to use a cart that belongs to a credentialed user? This is the
        // case when an anonymous user submitted the credentialed user's email to the cart (see
        // CartController::actionUpdate()), or when the cart was loaded via a valid load-cart token
        // (see CartController::actionLoadCart()).
        $authorizedForCredentialedCart = $cart && \Craft::$app->getSession()->get('commerce:anonymousCartWithCredentialedCustomer:' . $cart->number, false);

        if ($cart && $cartCustomer && $cartCustomer->getIsCredentialed() &&
            !$authorizedForCredentialedCart &&
            (
                // Forget cart if they are not logged-in.
                !$currentUser
                ||
                // Forget cart if the logged-in user is not the same as the cart customer.
                $currentUser->id != $cartCustomer->id
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
        $this->cart = null;
        // Force a new cart number to be generated when next requested.
        $this->cartNumber = false;
        if (!\Craft::$app->getRequest()->getIsConsoleRequest()) {
            $cookie = \Craft::createObject(array_merge($this->cartCookie, [
                'class' => Cookie::class,
            ]));

            \Craft::$app->getResponse()->getCookies()->remove($cookie, true);
        }
    }

    /**
     * Generates a new random cart number and returns it.
     */
    public function generateCartNumber(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Calculates the date of the active cart duration edge.
     */
    public function getActiveCartEdgeDuration(): string
    {
        $edge = new DateTime();
        $activeCartDuration = Config::durationInSeconds(Plugin::getInstance()->getSettings()->activeCartDuration);
        $interval = \craft\helpers\DateTimeHelper::secondsToInterval($activeCartDuration);
        $edge->sub($interval);
        return $edge->format(DateTime::ATOM);
    }

    /**
     * Returns whether there is a cart number in the session.
     */
    public function getHasSessionCartNumber(): bool
    {
        if ($this->cartNumber === false) {
            return false;
        }

        if ($this->cartNumber === null) {
            $request = \Craft::$app->getRequest();
            $requestCookies = $request->getCookies();

            return $requestCookies->getValue($this->cartCookie['name'], false) !== false;
        }

        return true;
    }

    /**
     * Get the session cart number or generates one if none exists.
     */
    protected function getSessionCartNumber(): string
    {
        if (!\Craft::$app->getRequest()->getIsConsoleRequest()) {
            $request = \Craft::$app->getRequest();
            $requestCookies = $request->getCookies();

            // Only try to retrieve the cart number from the cookie if `cartNumber` is `null`.
            if ($this->cartNumber === null && $cookieNumber = $requestCookies->getValue($this->cartCookie['name'])) {
                $this->cartNumber = $cookieNumber;
            }
        }

        // A `null` or `false` value means we need to generate a new cart number.
        if ($this->cartNumber === null || $this->cartNumber === false) {
            $this->cartNumber = $this->generateCartNumber();
        }

        // Just in case the current cart is not the one in session, clear the cached cart.
        if ($this->cart && $this->cart->number !== $this->cartNumber) {
            $this->cart = null;
        }

        return $this->cartNumber;
    }

    /**
     * Set the session cart number.
     */
    public function setSessionCartNumber(string $cartNumber): void
    {
        if (!\Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->cartNumber = $cartNumber;
            $cookie = \Craft::createObject(array_merge($this->cartCookie, [
                'class' => Cookie::class,
                'value' => $cartNumber,
                'expire' => time() + $this->cartCookieDuration,
            ]));
            \Craft::$app->getResponse()->getCookies()->add($cookie);
        }
    }

    /**
     * Returns a URL to load a cart with a secure token.
     *
     * @param Order $cart The cart to generate the load URL for
     * @return string The URL with secure token
     */
    public function getLoadCartUrl(Order $cart): string
    {
        $linkExpiry = Plugin::getInstance()->getSettings()->loadCartUrlExpiry;
        $expiryDate = \craft\helpers\DateTimeHelper::currentUTCDateTime()->add(\craft\helpers\DateTimeHelper::secondsToInterval($linkExpiry));

        $token = \Craft::$app->getTokens()->createToken([
            'commerce/cart/load-cart',
            ['cartNumber' => $cart->number],
        ], expiryDate: $expiryDate);

        $request = \Craft::$app->getRequest();
        $isCpRequest = $request->getIsCpRequest();
        $request->setIsCpRequest(false);
        $url = Url::actionUrl('commerce/cart/load-cart', [
            'number' => $cart->number,
            'code' => $token,
        ]);
        $request->setIsCpRequest($isCpRequest);

        return $url;
    }

    /**
     * Restores previous cart for the current user if their current cart is empty.
     * Ideally this is only used when a user logs in.
     */
    public function restorePreviousCartForCurrentUser(): void
    {
        $currentUser = \Craft::$app->getUser()->getIdentity();
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
            $this->cart = $previousCartsWithLineItems;
            $this->setSessionCartNumber($previousCartsWithLineItems->number);
        } elseif ($anyPreviousCart) {
            // Finally try to restore any other previous cart for the customer
            $this->cart = $anyPreviousCart;
            $this->setSessionCartNumber($anyPreviousCart->number);
        }
    }

    /**
     * Removes all carts that are incomplete and older than the config setting.
     *
     * @return int The number of carts purged from the database
     */
    public function purgeIncompleteCarts(): int
    {
        if (!Plugin::getInstance()->getSettings()->purgeInactiveCarts) {
            return 0;
        }

        $configInterval = Config::durationInSeconds(Plugin::getInstance()->getSettings()->purgeInactiveCartsDuration);
        $edge = new DateTime();
        $interval = \craft\helpers\DateTimeHelper::secondsToInterval($configInterval);
        $edge->sub($interval);

        // This query is exposed via CartPurgeEvent::$inactiveCartsQuery as a legacy craft\db\Query,
        // so it can't be swapped for the Laravel query builder without breaking that event's contract.
        $cartIdsQuery = new Query()
            ->select(['orders.id'])
            ->where(['not', ['isCompleted' => true]])
            ->andWhere('[[orders.dateUpdated]] <= :edge', ['edge' => CraftDb::prepareDateForDb($edge)])
            ->from(['orders' => Table::ORDERS]);

        $event = new CartPurgeEvent(inactiveCartsQuery: $cartIdsQuery);

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getCarts()->hasEventHandlers(self::EVENT_BEFORE_PURGE_INACTIVE_CARTS)) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getCarts()->trigger(self::EVENT_BEFORE_PURGE_INACTIVE_CARTS, $event);
        }

        if (!$event->isValid) {
            return 0;
        }

        // Taken from craft\services\Elements::deleteElement(); Using the method directly
        // takes too many resources since it retrieves the order before deleting it.
        // Delete the elements table rows, which will cascade across all other InnoDB tables
        \Craft::$app->getDb()->createCommand()
            ->delete('{{%elements}}', ['id' => $event->inactiveCartsQuery])
            ->execute();

        // The searchindex table is probably MyISAM, though
        \Craft::$app->getDb()->createCommand()
            ->delete('{{%searchindex}}', ['elementId' => $event->inactiveCartsQuery])
            ->execute();

        return (int)$cartIdsQuery->count();
    }

    protected function loadCookie(): void
    {
        $currentStore = Plugin::getInstance()->getStores()->getCurrentStore();

        // Complete the cart cookie config
        if (!isset($this->cartCookie['name'])) {
            $this->cartCookie['name'] = md5(sprintf('Craft.%s.%s.%s', self::class, \Craft::$app->id, $currentStore->handle)) . '_commerce_cart';
        }

        // Don't restore from cookie if the cart was explicitly forgotten this request.
        if ($this->cartNumber === false) {
            return;
        }

        $request = \Craft::$app->getRequest();
        if (!$request->getIsConsoleRequest()) {
            $this->cartCookie = \Craft::cookieConfig($this->cartCookie);

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
    private function getCartPaymentCurrencyIso(): string
    {
        if ($this->cart) {
            // Is the payment currency locked to the constant
            if (defined('COMMERCE_PAYMENT_CURRENCY')) {
                $paymentCurrencies = app(PaymentCurrencies::class)->getAllPaymentCurrencies($this->cart->storeId);
                // if not in array
                if (!$paymentCurrencies->contains('iso', '==', COMMERCE_PAYMENT_CURRENCY)) {
                    throw new \yii\base\InvalidConfigException('The COMMERCE_PAYMENT_CURRENCY constant is not set to a valid payment currency.');
                }

                $this->cart->paymentCurrency = COMMERCE_PAYMENT_CURRENCY;
            }

            return $this->cart->paymentCurrency;
        }

        return app(PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso();
    }

    public function afterSaveUserHandler(ModelEvent $event): void
    {
        $segments = \Craft::$app->getRequest()->getActionSegments();
        $userSaveSegments = ['users', 'save-user'];
        $isUserSaveAction = $segments == $userSaveSegments;

        // we have a cart number, currently anon, and the current action being executed is user save
        if (!\Craft::$app->getUser()->getIdentity() &&
            !\Craft::$app->getRequest()->getIsCpRequest() &&
            $isUserSaveAction
        ) {
            $currentCartNumber = $this->getSessionCartNumber();
            // Set the session flag to preserve the cart for this user
            \Craft::$app->getSession()->set('commerce:anonymousCartWithCredentialedCustomer:' . $currentCartNumber, true);
        }
    }
}
