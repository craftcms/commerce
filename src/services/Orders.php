<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\adjusters\Discount;
use craft\commerce\adjusters\Shipping;
use craft\commerce\adjusters\Tax;
use craft\commerce\base\AdjusterInterface;
use craft\commerce\elements\Order;
use craft\commerce\events\OrderEvent;
use craft\commerce\helpers\Currency;
use craft\commerce\models\Address;
use craft\commerce\models\Customer;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\commerce\records\LineItem as LineItemRecord;
use craft\commerce\records\Order as OrderRecord;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use yii\base\Component;
use yii\base\Exception;

/**
 * Orders service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Orders extends Component
{

    // Constants
    // =========================================================================

    /**
     * @event OrderEvent The event that is raised before an order is saved.
     */
    const EVENT_BEFORE_SAVE_ORDER = 'beforeSaveOrder';

    /**
     * @event OrderEvent The event that is raised after an order is saved.
     */
    const EVENT_AFTER_SAVE_ORDER = 'afterSaveOrder';

    // Public Methods
    // =========================================================================

    /**
     * @param int $id
     *
     * @return Order|null
     */
    public function getOrderById($id)
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
     *
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
     *
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
     *
     * @return Order[]
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
