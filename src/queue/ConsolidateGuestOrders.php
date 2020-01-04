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
            ->where(['[[orders.email]]' => $this->email])
            ->andWhere(['[[orders.isCompleted]]' => true])
            // we want the customers related to a userId to be listed first, then by their latest order
            ->orderBy('[[customers.userId]] DESC, [[orders.dateOrdered]] ASC')
            ->scalar(); // get the first customerId in the result

        if (!$customerId) {
            return;
        }

        // Get completed orders for other customers with the same email but not the same customer
        $orders = (new Query())
            ->select(['[[orders.id]] id ', '[[customers.userId]] userId'])
            ->where(['and', ['[[orders.email]]' => $this->email, '[[orders.isCompleted]]' => true], ['not', ['[[orders.customerId]]' => $customerId]]])
            ->leftJoin(Table::CUSTOMERS . ' customers', '[[orders.customerId]] = [[customers.id]]')
            ->from(Table::ORDERS . ' orders')
            ->all();

        $total = count($orders) + 1;
        $step = 1;

        foreach ($orders as $order) {

            $userId = $order['userId'];
            $orderId = $order['id'];

            $this->setProgress($queue, $step / $total, Plugin::t('Order {step} of {total}', compact('step', 'total')));

            if (!$userId) {
                // Dont use element save, just update DB directly
                Craft::$app->getDb()->createCommand()
                    ->update('{{%commerce_orders}} orders', ['[[orders.customerId]]' => $customerId], ['[[orders.id]]' => $orderId])
                    ->execute();
            }

            $step++;
        }

        // Now that we have a much of customers that no longer have an order, clean up the orphaned customers.
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