<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\CartsDeprecatedTrait;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

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
     * @return Order
     * @throws Exception
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     */
    public function getCart(): Order
    {
        if (null === $this->_cart) {
            $number = $this->getSessionCartNumber();

            $cart = Order::find()->isCompleted(false)->number($number)->one();
            if ($this->_cart = $cart) {
                // We do not want to use the same order number as a completed order.
                if ($this->_cart->isCompleted) {
                    $this->forgetCart();
                    Plugin::getInstance()->getCustomers()->forgetCustomer();
                    $this->getCart();
                }
            } else {
                $this->_cart = new Order();
                $this->_cart->number = $number;
                Craft::$app->getElements()->saveElement($this->_cart, false);
            }
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
     * @throws \Throwable
     */
    public function purgeIncompleteCarts(): int
    {
        $doPurge = Plugin::getInstance()->getSettings()->purgeInactiveCarts;

        if ($doPurge) {
            $cartIds = $this->_getCartsIdsToPurge();
            foreach ($cartIds as $id) {
                Craft::$app->getElements()->deleteElementById($id);
            }

            return \count($cartIds);
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
        $edge = new \DateTime();
        $interval = new \DateInterval($configInterval);
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
