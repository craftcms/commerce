<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use craft\commerce\elements\Order;
use craft\commerce\models\Customer;
use yii\base\Component;

/**
 * Orders service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Orders extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Get an order by its ID.
     *
     * @param int $id
     * @return Order|null
     */
    public function getOrderById(int $id)
    {
        if (!$id) {
            return null;
        }

        $query = Order::find();
        $query->id($id);
        $query->status(null);

        return $query->one();
    }

    /**
     * Get an order by its number.
     *
     * @param string $number
     * @return Order|null
     */
    public function getOrderByNumber($number)
    {
        $query = Order::find();
        $query->number($number);

        return $query->one();
    }

    /**
     * Get all orders by their customer.
     *
     * @param int|Customer $customer
     * @return Order[]|null
     */
    public function getOrdersByCustomer($customer)
    {
        $query = Order::find();
        if ($customer instanceof Customer) {
            $query->customer($customer);
        } else {
            $query->customerId($customer);
        }
        $query->isCompleted(true);
        $query->limit(null);

        return $query->all();
    }

    /**
     * Get all orders by their email.
     *
     * @param string $email
     * @return Order[]|null
     */
    public function getOrdersByEmail($email)
    {
        $query = Order::find();
        $query->email($email);
        $query->isCompleted(true);
        $query->limit(null);

        return $query->all();
    }
}
