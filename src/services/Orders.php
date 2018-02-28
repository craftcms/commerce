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
     * @param int|Customer $customer
     * @return Order[]|null
     */
    public function getOrdersByCustomer($customer)
    {
        $query = Order::find();
        $query->customer($customer);
        $query->isCompleted(true);
        $query->limit(null);

        return $query->all();
    }

    /**
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
