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
            ->select('[[orders.customerId]]')
            ->from(Table::ORDERS . ' orders')
            ->innerJoin(Table::CUSTOMERS . ' customers', '[[customers.id]] = [[orders.customerId]]')
            ->where(['email' => $this->email])
            ->andWhere(['isCompleted' => 1])
            // we want the customers related to a userId to be listed first, then by latest order
            ->orderBy('[[customers.userId]] DESC, [[orders.dateOrdered]] ASC')
            ->scalar();

        if (!$customerId) {
            return;
        }

        // Get completed orders for other customers with the same email
        $orders = Order::find()
            ->isCompleted(1)
            ->customerId('not ' . $customerId)
            ->email($this->email)
            ->all();

        $total = count($orders) + 1;
        $step = 1;

        foreach ($orders as $order) {
            $this->setProgress($queue, $step / $total, Plugin::t('Order {step} of {total}', compact('step', 'total')));

            $belongsToAnotherUser = $order->getCustomer() && $order->getCustomer()->getUser();

            if (!$belongsToAnotherUser) {
                // Dont use element save, just update DB directly
                Craft::$app->getDb()->createCommand()->update('{{%commerce_orders}} orders', ['customerId' => $customerId], ['[[orders.id]]' => $order->id]);
            }

            $step++;
        }

        $this->setProgress($queue, $step / $total, Plugin::t('Purging orphaned customers.'));
        Plugin::getInstance()->getCustomers()->purgeOrphanedCustomers();
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