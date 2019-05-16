<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
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
     * @return Order
     * @throws Exception
     * @throws Throwable
     * @throws ElementNotFoundException
     */
    public function getCart($forceSave = false): Order
    {
        if (null === $this->_cart) {
            $number = $this->getSessionCartNumber();

            $cart = Order::find()->number($number)->trashed(null)->one();
            if ($this->_cart = $cart) {
                // We do not want to use the same order number as a completed order.
                if ($this->_cart->isCompleted || $this->_cart->trashed) {
                    $this->forgetCart();
                    Plugin::getInstance()->getCustomers()->forgetCustomer();
                    return $this->getCart($forceSave);
                }
            } else {
                $this->_cart = new Order();
                $this->_cart->number = $number;
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
     * Get the session cart number.
     *
     * @return mixed|string
     */
    private function getSessionCartNumber()
    {
        $session = Craft::$app->getSession();
        $cartNumber = $session[$this->cartName];

        if (!$cartNumber) {
            $cartNumber = $this->generateCartNumber();
            $session->set($this->cartName, $cartNumber);
        }

        return $cartNumber;
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
