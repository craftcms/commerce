<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\queue\jobs;

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
     * @var array
     */
    public $emails;

    /**
     * @var
     */
    private $_queue;

    /**
     * @inheritDoc
     */
    public function execute($queue)
    {
        $this->_queue = $queue;

        $total = count($this->emails);

        $step = 1;

        foreach ($this->emails as $email) {
            $this->setProgress($this->_queue, $step / $total, Plugin::t('Email {step} of {total}', compact('step', 'total')));
            try {
                Plugin::getInstance()->getCustomers()->consolidateGuestOrdersByEmail($email);
            } catch (\Throwable $e) {
                Craft::warning('Could not consolidate orders for guest email'.$email, 'commerce');
            }

            $step++;
        }

        $this->setProgress($queue, $step / $total, Plugin::t('Purging orphaned customers.'));
        Plugin::getInstance()->getCustomers()->purgeOrphanedCustomers();
    }

    /**
     * @deprecated in 3.1.4. Use [[\craft\commerce\services\Customers::consolidateGuestOrdersByEmail()]] instead.
     */
    public function consolidate($email)
    {
        $customerId = (new Query())
            ->select('[[orders.customerId]]')
            ->from(Table::ORDERS . ' orders')
            ->innerJoin(Table::CUSTOMERS . ' customers', '[[customers.id]] = [[orders.customerId]]')
            ->where(['[[orders.email]]' => $email])
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
            ->where(['and', ['[[orders.email]]' => $email, '[[orders.isCompleted]]' => true], ['not', ['[[orders.customerId]]' => $customerId]]])
            ->leftJoin(Table::CUSTOMERS . ' customers', '[[orders.customerId]] = [[customers.id]]')
            ->from(Table::ORDERS . ' orders')
            ->all();

        foreach ($orders as $order) {
            $userId = $order['userId'];
            $orderId = $order['id'];

            if (!$userId) {
                // Dont use element save, just update DB directly
                Craft::$app->getDb()->createCommand()
                    ->update(Table::ORDERS, [
                        'customerId' => $customerId,
                    ], [
                        'id' => $orderId,
                    ])
                    ->execute();
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Plugin::t('Consolidate all guest orders.');
    }
}
