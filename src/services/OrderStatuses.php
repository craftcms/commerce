<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\DefaultOrderStatusEvent;
use craft\commerce\events\EmailEvent;
use craft\commerce\models\OrderHistory;
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin;
use craft\commerce\records\OrderStatus as OrderStatusRecord;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use DateTime;
use Throwable;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\web\ServerErrorHttpException;
use function count;

/**
 * Order status service.
 *
 * @property OrderStatus|null $defaultOrderStatus default order status from the DB
 * @property OrderStatus[]|array $allOrderStatuses all Order Statuses
 * @property null|int $defaultOrderStatusId default order status ID from the DB
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderStatuses extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event DefaultOrderStatusEvent The event that is triggered when getting a default status for an order.
     * You may set [[DefaultOrderStatusEvent::orderStatus]] to a desired OrderStatus to override the default status set in CP
     *
     * Plugins can get notified when a default order status is being fetched
     *
     * ```php
     * use craft\commerce\events\DefaultOrderStatusEvent;
     * use craft\commerce\services\OrderStatuses;
     * use yii\base\Event;
     *
     * Event::on(OrderStatuses::class, OrderStatuses::EVENT_DEFAULT_ORDER_STATUS, function(DefaultOrderStatusEvent $e) {
     *     // Do something - perhaps figure out a better default order statues than the one set in CP
     * });
     * ```
     */
    const EVENT_DEFAULT_ORDER_STATUS = 'defaultOrderStatus';

    const CONFIG_STATUSES_KEY = 'commerce.orderStatuses';

    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    private $_fetchedAllStatuses = false;

    /**
     * @var OrderStatus[]
     */
    private $_orderStatusesById = [];

    /**
     * @var OrderStatus[]
     */
    private $_orderStatusesByHandle = [];

    /**
     * @var OrderStatus
     */
    private $_defaultOrderStatus;

    // Public Methods
    // =========================================================================

    /**
     * Get order status by its handle.
     *
     * @param string $handle
     * @return OrderStatus|null
     */
    public function getOrderStatusByHandle($handle)
    {
        if (isset($this->_orderStatusesByHandle[$handle])) {
            return $this->_orderStatusesByHandle[$handle];
        }

        if ($this->_fetchedAllStatuses) {
            return null;
        }

        $result = $this->_createOrderStatusesQuery()
            ->where(['handle' => $handle])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeOrderStatus(new OrderStatus($result));

        return $this->_orderStatusesByHandle[$handle];
    }

    /**
     * Get default order status ID from the DB
     *
     * @return int|null
     */
    public function getDefaultOrderStatusId()
    {
        $defaultStatus = $this->getDefaultOrderStatus();

        if ($defaultStatus && $defaultStatus->id) {
            return $defaultStatus->id;
        }

        return null;
    }

    /**
     * Get default order status from the DB
     *
     * @return OrderStatus|null
     */
    public function getDefaultOrderStatus()
    {
        if ($this->_defaultOrderStatus !== null) {
            return $this->_defaultOrderStatus;
        }

        $result = $this->_createOrderStatusesQuery()
            ->where(['default' => 1])
            ->one();

        return new OrderStatus($result);
    }

    /**
     * Get the default order status for a particular order. Defaults to the CP configured default order status.
     *
     * @param Order $order
     * @return OrderStatus|null
     */
    public function getDefaultOrderStatusForOrder(Order $order)
    {
        $orderStatus = $this->getDefaultOrderStatus();

        $event = new DefaultOrderStatusEvent();
        $event->orderStatus = $orderStatus;
        $event->order = $order;

        $this->trigger(self::EVENT_DEFAULT_ORDER_STATUS, $event);

        return $event->orderStatus;
    }

    /**
     * Save the order status.
     *
     * @param OrderStatus $orderStatus
     * @param array $emailIds
     * @param bool $runValidation should we validate this order status before saving.
     * @return bool
     * @throws Exception
     */
    public function saveOrderStatus(OrderStatus $orderStatus, array $emailIds = [], bool $runValidation = true): bool
    {
        $isNewStatus = !(bool)$orderStatus->id;

        if ($runValidation && !$orderStatus->validate()) {
            Craft::info('Order status not saved due to validation error.', __METHOD__);

            return false;
        }

        if ($isNewStatus) {
            $statusUid = StringHelper::UUID();
        } else {
            $statusUid = Db::uidById('{{%commerce_orderstatuses}}', $orderStatus->id);
        }

        // Make sure no statuses that are not archived share the handle
        $existingStatus = $this->getOrderStatusByHandle($orderStatus->handle);

        if ($existingStatus && (!$orderStatus->id || $orderStatus->id !== $existingStatus->id)) {
            $orderStatus->addError('handle', Craft::t('commerce', 'That handle is already in use'));
            return false;
        }

        $projectConfig = Craft::$app->getProjectConfig();

        if ($orderStatus->isArchived) {
            $configData = null;
        } else {
            $emails = Db::uidsByIds('{{%commerce_emails}}', $emailIds);
            $configData = [
                'name' => $orderStatus->name,
                'handle' => $orderStatus->handle,
                'color' => $orderStatus->color,
                'sortOrder' => (int)($orderStatus->sortOrder ?? 99),
                'default' => (bool)$orderStatus->default,
                'emails' => array_combine($emails, $emails)
            ];
        }

        $configPath = self::CONFIG_STATUSES_KEY . '.' . $statusUid;
        $projectConfig->set($configPath, $configData);

        if ($isNewStatus) {
            $orderStatus->id = Db::idByUid('{{%commerce_orderstatuses}}', $statusUid);
        }

        return true;
    }

    /**
     * Handle order status change.
     *
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable if reasons
     */
    public function handleChangedOrderStatus(ConfigEvent $event)
    {
        $statusUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $statusRecord = $this->_getOrderStatusRecord($statusUid);

            $statusRecord->name = $data['name'];
            $statusRecord->handle = $data['handle'];
            $statusRecord->color = $data['color'];
            $statusRecord->sortOrder = $data['sortOrder'] ?? 99;
            $statusRecord->default = $data['default'];
            $statusRecord->uid = $statusUid;

            // Save the volume
            $statusRecord->save(false);

            if ($statusRecord->default) {
                OrderStatusRecord::updateAll(['default' => 0], ['not', ['id' => $statusRecord->id]]);
            }

            $connection = Craft::$app->getDb();
            $connection->createCommand()->delete('{{%commerce_orderstatus_emails}}', ['orderStatusId' => $statusRecord->id])->execute();

            if (!empty($data['emails'])) {
                foreach ($data['emails'] as $emailUid) {
                    Craft::$app->projectConfig->processConfigChanges(Emails::CONFIG_EMAILS_KEY . '.' . $emailUid);
                }

                $emailIds = Db::idsByUids('{{%commerce_emails}}', $data['emails']);

                foreach ($emailIds as $emailId) {
                    $connection->createCommand()
                        ->insert('{{%commerce_orderstatus_emails}}', [
                            'orderStatusId' => $statusRecord->id,
                            'emailId' => $emailId
                        ])
                        ->execute();
                }
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Archive an order status by it's id.
     *
     * @param int $id
     * @return bool
     * @throws Throwable
     */
    public function archiveOrderStatusById(int $id): bool
    {
        $statuses = $this->getAllOrderStatuses();
        $status = $this->getOrderStatusById($id);

        if (count($statuses) >= 2 && $status) {
            $status->isArchived = true;
            return $this->saveOrderStatus($status);
        }

        return false;
    }


    /**
     * Handle order status being archived
     *
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable if reasons
     */
    public function handleArchivedOrderStatus(ConfigEvent $event)
    {
        $orderStatusUid = $event->tokenMatches[0];

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $orderStatusRecord = $this->_getOrderStatusRecord($orderStatusUid);

            $orderStatusRecord->isArchived = true;
            $orderStatusRecord->dateArchived = Db::prepareDateForDb(new DateTime());

            // Save the volume
            $orderStatusRecord->save(false);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Prune a deleted email from order statuses.
     *
     * @param EmailEvent $event
     */
    public function pruneDeletedEmail(EmailEvent $event)
    {
        $emailUid = $event->email->uid;

        $projectConfig = Craft::$app->getProjectConfig();
        $statuses = $projectConfig->get(self::CONFIG_STATUSES_KEY);

        // Loop through the volumes and prune the UID from field layouts.
        if (is_array($statuses)) {
            foreach ($statuses as $orderStatusUid => $orderStatus) {
                $projectConfig->remove(self::CONFIG_STATUSES_KEY . '.' . $orderStatusUid . '.emails.' . $emailUid);
            }
        }
    }

    /**
     * Returns all Order Statuses
     *
     * @return OrderStatus[]
     */
    public function getAllOrderStatuses(): array
    {
        if (!$this->_fetchedAllStatuses) {
            $results = $this->_createOrderStatusesQuery()->all();

            foreach ($results as $row) {
                $this->_memoizeOrderStatus(new OrderStatus($row));
            }

            $this->_fetchedAllStatuses = true;
        }

        return $this->_orderStatusesById;
    }

    /**
     * Handler for order status change event
     *
     * @param Order $order
     * @param OrderHistory $orderHistory
     */
    public function statusChangeHandler($order, $orderHistory)
    {
        if ($order->orderStatusId) {
            $status = $this->getOrderStatusById($order->orderStatusId);
            if ($status && count($status->emails)) {
                foreach ($status->emails as $email) {
                    Plugin::getInstance()->getEmails()->sendEmail($email, $order, $orderHistory);
                }
            }
        }
    }

    /**
     * Get an order status by ID
     *
     * @param int $id
     * @return OrderStatus|null
     */
    public function getOrderStatusById($id)
    {
        if (isset($this->_orderStatusesById[$id])) {
            return $this->_orderStatusesById[$id];
        }

        if ($this->_fetchedAllStatuses) {
            return null;
        }

        $result = $this->_createOrderStatusesQuery()
            ->where(['id' => $id])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeOrderStatus(new OrderStatus($result));

        return $this->_orderStatusesById[$id];
    }

    /**
     * Reorders the order statuses.
     *
     * @param array $ids
     * @return bool
     * @throws Exception
     * @throws ErrorException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function reorderOrderStatuses(array $ids): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $uidsByIds = Db::uidsByIds('{{%commerce_orderstatuses}}', $ids);

        foreach ($ids as $orderStatus => $statusId) {
            if (!empty($uidsByIds[$statusId])) {
                $statusUid = $uidsByIds[$statusId];
                $projectConfig->set(self::CONFIG_STATUSES_KEY . '.' . $statusUid . '.sortOrder', $orderStatus + 1);
            }
        }

        return true;
    }

    // Private methods
    // =========================================================================

    /**
     * Memoize an order status  by its ID and handle.
     *
     * @param OrderStatus $orderStatus
     */
    private function _memoizeOrderStatus(OrderStatus $orderStatus)
    {
        $this->_orderStatusesById[$orderStatus->id] = $orderStatus;
        $this->_orderStatusesByHandle[$orderStatus->handle] = $orderStatus;
    }

    /**
     * Returns a Query object prepped for retrieving order statuses
     *
     * @return Query
     */
    private function _createOrderStatusesQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'color',
                'sortOrder',
                'default',
                'uid',
            ])
            ->where(['isArchived' => false])
            ->orderBy('sortOrder')
            ->from(['{{%commerce_orderstatuses}}']);
    }

    /**
     * Gets an order status' record by uid.
     *
     * @param string $uid
     * @return OrderStatusRecord
     */
    private function _getOrderStatusRecord(string $uid): OrderStatusRecord
    {
        if ($orderStatus = OrderStatusRecord::findOne(['uid' => $uid])) {
            return $orderStatus;
        }

        return new OrderStatusRecord();
    }
}
