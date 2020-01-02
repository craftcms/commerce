<?php

namespace craft\commerce\queue;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\queue\BaseJob;
use craft\queue\QueueInterface;

/**
 * ConsolidateGuestOrders job
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class ConsolidateGuestOrders extends BaseJob
{

    /**
     * @var string
     */
    public $email;

    /**
     * @inheritDoc
     */
    public function execute($queue)
    {
        $customerId = (new Query())
            ->select('orders.customerId')
            ->from(Table::ORDERS . ' orders')
            ->innerJoin(Table::CUSTOMERS . ' customers', 'customers.id = orders.customerId')
            ->where(['email' => $this->email])
            ->andWhere(['isCompleted' => 1])
            // If they have a user account make sure we associate the orders
            // to that customer
            ->orderBy('userId DESC, dateOrdered ASC')
            ->scalar();

        if (!$customerId)
        {
            return;
        }

        $ordersQuery = Order::find()
            ->isCompleted(1)
            ->customerId('not ' . $customerId)
            ->email($this->email);

        $total = $ordersQuery->count();
        $orders = $ordersQuery->all();
        $step = 1;

        foreach ($orders as $order) {
            $this->setProgress($queue, $step / $total, Plugin::t('Order {step} of {total}', compact('step', 'total')));

            $belongsToAnotherUser = $order->getCustomer() && $order->getCustomer()->getUser();

            if (!$belongsToAnotherUser) {
                $order->customerId = $customerId;
                Craft::$app->elements->saveElement($order, false);
            }

            $step++;
        }
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Plugin::t('Consolidate all guest orders for customer.');
    }
}