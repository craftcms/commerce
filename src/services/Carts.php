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
use craft\helpers\ConfigHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use DateTime;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use function count;

/**
 * Cart service. This manages the cart currently in the session.
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
     * @var string Session key for storing the cart number
     */
    protected $cartName = 'commerce_cart';

    /**
     * @var Order
     */
    private $_cart;


    /**
     * Get the current cart for this session.
     *
     * @param bool $forceSave Force the cart to save when requesting it.
     * @return Order
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function getCart($forceSave = false): Order
    {
        // If there is no cart set for this request, and we can't get a cart from session, create one.
        if (null === $this->_cart && !$this->_cart = $this->_getCart()) {
            $this->_cart = new Order();
            $this->_cart->number = $this->getSessionCartNumber();
        }

        // Ensure the session knows what the current cart is.
        $this->setSessionCartNumber($this->_cart->number);

        // Track the things that might change on this cart
        $originalIp = $this->_cart->lastIp;
        $originalOrderLanguage = $this->_cart->orderLanguage;
        $originalCurrency = $this->_cart->currency;
        $originalPaymentCurrency = $this->_cart->paymentCurrency;
        $originalCustomerId = $this->_cart->customerId;

        // These values should always be kept up to date when a cart is retrieved from session.
        $this->_cart->lastIp = Craft::$app->getRequest()->userIP;
        $this->_cart->orderLanguage = Craft::$app->language;
        $this->_cart->currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $this->_cart->paymentCurrency = $this->_getCartPaymentCurrencyIso();
        $this->_cart->customerId = Plugin::getInstance()->getCustomers()->getCustomerId();
        $this->_cart->origin = Order::ORIGIN_WEB;

        $changedIp = $originalIp != $this->_cart->lastIp;
        $changedOrderLanguage = $originalOrderLanguage != $this->_cart->orderLanguage;
        $changedCurrency = $originalCurrency != $this->_cart->currency;
        $changedPaymentCurrency = $originalPaymentCurrency != $this->_cart->paymentCurrency;
        $changedCustomerId = $originalCustomerId != $this->_cart->customerId;

        // Has the customer in session changed?
        if ($changedCustomerId) {
            // Don't lose the data from the address, just drop the ID so when the order is saved, the address belongs to the new customer of the order
            if ($this->_cart->billingAddressId && $billingAddress = Plugin::getInstance()->getAddresses()->getAddressById($this->_cart->billingAddressId)) {
                $billingAddress->id = null;
                $this->_cart->setBillingAddress($billingAddress);
            }
            // Don't lose the data from the address, just drop the ID so when the order is saved, the address belongs to the new customer of the order
            if ($this->_cart->shippingAddressId && $shippingAddress = Plugin::getInstance()->getAddresses()->getAddressById($this->_cart->shippingAddressId)) {
                $shippingAddress->id = null;
                $this->_cart->setShippingAddress($shippingAddress);
            }
        }

        $somethingChangedOnTheCart = ($changedIp || $changedOrderLanguage || $changedCurrency || $changedCustomerId || $changedPaymentCurrency);

        // If the cart has already been saved (has an ID), then only save if something else changed.
        if (($this->_cart->id && $somethingChangedOnTheCart) || $forceSave) {
            Craft::$app->getElements()->saveElement($this->_cart, false);
        }

        return $this->_cart;
    }

    /**
     * @return Order|null
     * @throws Exception
     * @throws MissingComponentException
     * @throws Throwable
     */
    private function _getCart()
    {
        $cart = null;
        $currentUser = Craft::$app->getUser()->getIdentity();
        $isNumberCartInSession = $this->getHasSessionCartNumber();

        // Load the current cart if there is a cart number in the session
        if ($isNumberCartInSession) {
            $number = $this->getSessionCartNumber();
            // Get the cart based on the number in the session.
            // It might be completed or trashed, but we still want to load it so we can determine this and forget it.
            $cart = Order::find()->number($number)->one();
        }

        // If the cart is already completed or trashed, forget the cart and start again.
        if ($cart && ($cart->isCompleted || $cart->trashed)) {
            $this->forgetCart();
            Plugin::getInstance()->getCustomers()->forgetCustomer();
            $cart = null; // continue
        }

        // If the current cart is empty see if the logged in user has a previous cart
        if ($cart && $currentUser && $cart->getIsEmpty()) {
            // Get any cart that is not empty, is not trashed or complete, and belongings to the user
            if ($previousCart = Order::find()->user($currentUser)->isCompleted(false)->trashed(false)->hasLineItems()->one()) {
                $cart = $previousCart;
            }
        }

        return $cart;
    }

    /**
     * Forgets the cart in the current session.
     *
     * @return void
     * @throws MissingComponentException
     */
    public function forgetCart()
    {
        $this->_cart = null;
        Craft::$app->getSession()->remove($this->cartName);
    }

    /**
     * Generate a new random cart number and returns it.
     *
     * @return string
     * @since 2.0
     */
    public function generateCartNumber(): string
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * Calculates the date of the active cart duration edge.
     *
     * @return string
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
     * Returns whether there is a cart number in the session.
     *
     * @return bool
     * @throws MissingComponentException
     * @since 2.1.11
     */
    public function getHasSessionCartNumber(): bool
    {
        $session = Craft::$app->getSession();
        return ($session->getHasSessionId() || $session->getIsActive()) && $session->has($this->cartName);
    }

    /**
     * Get the session cart number or generates one if none exists.
     *
     * @return string
     * @throws MissingComponentException
     */
    private function getSessionCartNumber(): string
    {
        $session = Craft::$app->getSession();
        $cartNumber = $session->get($this->cartName);

        if (!$cartNumber) {
            $cartNumber = $this->generateCartNumber();
            $session->set($this->cartName, $cartNumber);
        }

        return $cartNumber;
    }

    /**
     * Set the session cart number.
     *
     * @param string $cartNumber
     * @return void
     * @throws MissingComponentException
     */
    private function setSessionCartNumber(string $cartNumber)
    {
        $session = Craft::$app->getSession();
        $session->set($this->cartName, $cartNumber);
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
        $doPurge = Plugin::getInstance()->getSettings()->purgeInactiveCarts;
        $configInterval = ConfigHelper::durationInSeconds(Plugin::getInstance()->getSettings()->purgeInactiveCartsDuration);

        if ($doPurge) {
            $edge = new DateTime();
            $interval = DateTimeHelper::secondsToInterval($configInterval);
            $edge->sub($interval);

            $cartIds = (new Query())
                ->select(['orders.id'])
                ->where(['not', ['isCompleted' => 1]])
                ->andWhere('[[orders.dateUpdated]] <= :edge', ['edge' => $edge->format('Y-m-d H:i:s')])
                ->from(['orders' => Table::ORDERS])
                ->column();

            // Taken from craft\services\Elements::deleteElement(); Using the method directly
            // takes too much resources since it retrieves the order before deleting it.

            // Delete the elements table rows, which will cascade across all other InnoDB tables
            Craft::$app->getDb()->createCommand()
                ->delete('{{%elements}}', ['id' => $cartIds])
                ->execute();

            // The searchindex table is probably MyISAM, though
            Craft::$app->getDb()->createCommand()
                ->delete('{{%searchindex}}', ['elementId' => $cartIds])
                ->execute();

            return count($cartIds);
        }

        return 0;
    }

    /**
     * Gets the current payment currency ISO code
     *
     * @return string
     */
    private function _getCartPaymentCurrencyIso(): string
    {
        if ($this->_cart) {
            // Is the payment currency locked to the constant
            if (defined('COMMERCE_PAYMENT_CURRENCY')) {
                $currency = StringHelper::toUpperCase(COMMERCE_PAYMENT_CURRENCY);
                if (in_array($currency, Plugin::getInstance()->getCurrencies()->getAllCurrencies(), false)) {

                    return COMMERCE_PAYMENT_CURRENCY;
                }
            }

            return $this->_cart->paymentCurrency;
        }

        return Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
    }
}
