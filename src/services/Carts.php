<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Order as OrderHelper;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\errors\ElementNotFoundException;
use DateInterval;
use DateTime;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use function count;

/**
 * Cart service.
 *
 * @property-read string|mixed $sessionCartNumber
 * @property Order $cart
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Carts extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var string Session key for storing the cart number
     */
    protected $cartName = 'commerce_cart';

    /**
     * @var Order
     */
    private $_cart;

    // Public Methods
    // =========================================================================

    /**
     * Get the current cart for this session.
     *
     * @param bool $forceSave Force the cart to save when requesting it.
     * @param bool $mergeAllCartsForUser
     * @return Order
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function getCart($forceSave = false, $mergeAllCartsForUser = false): Order
    {
        // If there is no cart set for this request already
        if (null === $this->_cart) {

            // If the user is logged in, but no cart number is in session, get the last cart for the user
            if (($user = Craft::$app->getUser()->getIdentity()) && !$this->getHasSessionCartNumber()) {
                // Get any cart that is not trashed or complete and belonging to user
                if ($lastCart = Order::find()->user($user)->isCompleted(false)->trashed(false)->one()) {
                    // We want this to be the cart for the session
                    $this->setSessionCartNumber($lastCart->number);
                }
            }

            // Get the cart session number. If none exists, it will create a new number.
            $number = $this->getSessionCartNumber();

            // Get the cart based on the number in the session
            $cart = Order::find()->number($number)->trashed(null)->one();

            // If this cart is already completed or trashed, forget the cart and start again.
            if ($cart && ($cart->isCompleted || $cart->trashed)) {
                $this->forgetCart();
                Plugin::getInstance()->getCustomers()->forgetCustomer();
                return $this->getCart($forceSave);
            }

            // If the current user is not the customer of the current cart, update the cart customer
            if ($user && $cart) {
                $customer = Plugin::getInstance()->getCustomers()->getCustomerByUserId($user->id);
                // If the current cart in the session doesn't belong to the logged in user, assign it to the logged in user.
                if ($customer && $customer->id && ($cart->customerId != $customer->id)) {
                    $cart->customerId = $customer->id;
                    Craft::$app->getElements()->saveElement($cart, false);
                }
            }

            // Recover previous carts of the current user and merge them
            // Get all previous carts for this current user
            if ($user && $mergeAllCartsForUser) {

                $allCustomerCarts = Order::find()->isCompleted(false)->trashed(false)->user($user)->inReverse()->all();

                if (count($allCustomerCarts) == 1) {
                    $cart = array_shift($allCustomerCarts);
                    $this->setSessionCartNumber($cart->number);
                } elseif (count($allCustomerCarts) > 1) {
                    // Always use the first cart as the users cart.
                    $cart = array_shift($allCustomerCarts);
                    $this->setSessionCartNumber($cart->number);

                    foreach ($allCustomerCarts as $previousCart) {
                        if ($cart->id != $previousCart->id) {
                            OrderHelper::mergeOrders($cart, $previousCart);
                        }
                    }
                }
            }


            $this->_cart = $cart;

            if (!$this->_cart) {
                $this->_cart = new Order();
                $this->_cart->number = $this->getSessionCartNumber();
            }
        }

        $originalIp = $this->_cart->lastIp;
        $originalOrderLanguage = $this->_cart->orderLanguage;
        $originalCurrency = $this->_cart->currency;
        $originalCustomerId = $this->_cart->customerId;

        // These values should always be kept up to date when a cart is retrieved from session.
        $this->_cart->lastIp = Craft::$app->getRequest()->userIP;
        $this->_cart->orderLanguage = Craft::$app->language;
        $this->_cart->currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        $this->_cart->customerId = Plugin::getInstance()->getCustomers()->getCustomerId();

        // Has the customer in session changed?
        if ($this->_cart->customerId != $originalCustomerId) {

            // Don't lose the data from the address, just drop the ID
            if ($this->_cart->billingAddressId && $address = Plugin::getInstance()->getAddresses()->getAddressById($this->_cart->billingAddressId)) {
                $address->id = null;
                $this->_cart->setBillingAddress($address);
            }

            // Don't lose the data from the address, just drop the ID
            if ($this->_cart->shippingAddressId && $address = Plugin::getInstance()->getAddresses()->getAddressById($this->_cart->shippingAddressId)) {
                $address->id = null;
                $this->_cart->setShippingAddress($address);
            }
        }

        $changedIp = $originalIp != $this->_cart->lastIp;
        $changedOrderLanguage = $originalOrderLanguage != $this->_cart->orderLanguage;
        $changedCurrency = $originalCurrency != $this->_cart->currency;
        $changedCustomerId = $originalCustomerId != $this->_cart->customerId;

        if ($this->_cart->id) {
            if ($changedCurrency || $changedOrderLanguage || $changedIp || $changedCustomerId) {
                Craft::$app->getElements()->saveElement($this->_cart, false);
            }
        } else if ($forceSave) {
            Craft::$app->getElements()->saveElement($this->_cart, false);
        }

        return $this->_cart;
    }

    /**
     * Returns the current cart with the contents of any previous carts for the current user merged in.
     *
     * @return Order
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function getMergedCart(): Order
    {
        return $this->getCart(true, true);
    }

    /**
     * Forgets a Cart
     *
     * @return void
     */
    public function forgetCart()
    {
        $this->_cart = null;
        Craft::$app->getSession()->remove($this->cartName);
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

        if ($doPurge) {
            $cartIds = $this->_getCartsIdsToPurge();

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
     * Generate a cart number and return it.
     *
     * @return string
     */
    public function generateCartNumber(): string
    {
        return md5(uniqid(mt_rand(), true));
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns whether there is a cart number in the session.
     *
     * @return bool
     * @since 2.1.11
     */
    public function getHasSessionCartNumber(): bool
    {
        $session = Craft::$app->getSession();
        return ($session->getHasSessionId() || $session->getIsActive()) && $session->has($this->cartName);
    }

    /**
     * Get the session cart number.
     *
     * @return string
     */
    private function getSessionCartNumber(): string
    {
        $session = Craft::$app->getSession();
        $cartNumber = $session->get($this->cartName);;

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
     */
    private function setSessionCartNumber(
        string $cartNumber
    ) {
        $session = Craft::$app->getSession();
        $session->set($this->cartName, $cartNumber);
    }

    /**
     * Return cart IDs to be deleted
     *
     * @return int[]
     * @throws \Exception
     */
    private function _getCartsIdsToPurge(): array
    {
        $configInterval = Plugin::getInstance()->getSettings()->purgeInactiveCartsDuration;
        $edge = new DateTime();
        $interval = new DateInterval($configInterval);
        $interval->invert = 1;
        $edge->add($interval);

        return (new Query())
            ->select(['orders.id'])
            ->where(['not', ['isCompleted' => 1]])
            ->andWhere('[[orders.dateUpdated]] <= :edge', ['edge' => $edge->format('Y-m-d H:i:s')])
            ->from(['orders' => '{{%commerce_orders}}'])
            ->column();
    }
}
