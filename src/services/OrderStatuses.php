<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\events\DefaultOrderStatusEvent;
use craft\commerce\events\EmailEvent;
use craft\commerce\models\OrderHistory;
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin;
use craft\commerce\queue\jobs\SendEmail;
use craft\commerce\records\OrderStatus as OrderStatusRecord;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
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
    /**
     * @event DefaultOrderStatusEvent The event that is triggered when a default order status is being fetched.
     *
     * Set the event object’s `orderStatus` property to override the default status set in the control panel.
     *
     * ```php
     * use craft\commerce\events\DefaultOrderStatusEvent;
     * use craft\commerce\services\OrderStatuses;
     * use craft\commerce\models\OrderStatus;
     * use craft\commerce\elements\Order;
     * use yii\base\Event;
     *
     * Event::on(
     *     OrderStatuses::class,
     *     OrderStatuses::EVENT_DEFAULT_ORDER_STATUS,
     *     function(DefaultOrderStatusEvent $event) {
     *         // @var OrderStatus $status
     *         $status = $event->orderStatus;
     *         // @var Order $order
     *         $order = $event->order;
     *
     *         // Choose a more appropriate order status than the control panel default
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_DEFAULT_ORDER_STATUS = 'defaultOrderStatus';

    const CONFIG_STATUSES_KEY = 'commerce.orderStatuses';


    /**
     * @var OrderStatus[]
     */
    private $_orderStatuses;


    /**
     * Returns all Order Statuses
     *
     * @param bool $withTrashed
     * @return OrderStatus[]
     * @since 2.2
     */
    public function getAllOrderStatuses($withTrashed = false): array
    {
        // Get the caches items if we have them cached, and the request is for non-trashed items
        if ($this->_orderStatuses !== null) {
            return $this->_orderStatuses;
        }

        $results = $this->_createOrderStatusesQuery($withTrashed)->all();
        $orderStatuses = [];

        foreach ($results as $row) {
            $orderStatuses[] = new OrderStatus($row);
        }

        return $orderStatuses;
    }

    /**
     * Get an order status by ID
     *
     * @param int $id
     * @return OrderStatus|null
     */
    public function getOrderStatusById($id)
    {
        return ArrayHelper::firstWhere($this->getAllOrderStatuses(), 'id', $id);
    }

    /**
     * Get order status by its handle.
     *
     * @param string $handle
     * @return OrderStatus|null
     */
    public function getOrderStatusByHandle($handle)
    {
        return ArrayHelper::firstWhere($this->getAllOrderStatuses(), 'handle', $handle, false);
    }

    /**
     * Get default order status from the DB
     *
     * @return OrderStatus|null
     */
    public function getDefaultOrderStatus()
    {
        return ArrayHelper::firstWhere($this->getAllOrderStatuses(), 'default', true, false);
    }

    /**
     * Get default order status ID from the DB
     *
     * @return int|null
     */
    public function getDefaultOrderStatusId()
    {
        $orderStatus = $this->getDefaultOrderStatus();

        return $orderStatus ? $orderStatus->id : null;
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

        $event = new DefaultOrderStatusEvent([
            'orderStatus' => $orderStatus,
            'order' => $order
        ]);

        if ($this->hasEventHandlers(self::EVENT_DEFAULT_ORDER_STATUS)) {
            $this->trigger(self::EVENT_DEFAULT_ORDER_STATUS, $event);
        }

        return $event->orderStatus;
    }

    /**
     * @return array
     * @since 3.x
     */
    public function getOrderCountByStatus()
    {
        $countGroupedByStatusId = (new Query())
            ->select(['[[o.orderStatusId]]', 'count(o.id) as orderCount'])
            ->where(['[[o.isCompleted]]' => true, '[[e.dateDeleted]]' => null])
            ->from([Table::ORDERS . ' o'])
            ->leftJoin([CraftTable::ELEMENTS . ' e'], '[[o.id]] = [[e.id]]')
            ->groupBy(['[[o.orderStatusId]]'])
            ->indexBy('orderStatusId')
            ->all();

        // For those not in the groupBy
        $allStatuses = $this->getAllOrderStatuses();
        foreach ($allStatuses as $status) {
            if (!isset($countGroupedByStatusId[$status->id])) {
                $countGroupedByStatusId[$status->id] = [
                    'orderStatusId' => $status->id,
                    'handle' => $status->handle,
                    'orderCount' => 0
                ];
            }

            // Make sure all have their handle
            $countGroupedByStatusId[$status->id]['handle'] = $status->handle;
        }

        return $countGroupedByStatusId;
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
            $statusUid = Db::uidById(Table::ORDERSTATUSES, $orderStatus->id);
        }

        // Make sure no statuses that are not archived share the handle
        $existingStatus = $this->getOrderStatusByHandle($orderStatus->handle);

        if ($existingStatus && (!$orderStatus->id || $orderStatus->id != $existingStatus->id)) {
            $orderStatus->addError('handle', Plugin::t('That handle is already in use'));
            return false;
        }

        $projectConfig = Craft::$app->getProjectConfig();

        if ($orderStatus->dateDeleted) {
            $configData = null;
        } else {
            $emails = Db::uidsByIds(Table::EMAILS, $emailIds);
            $configData = [
                'name' => $orderStatus->name,
                'handle' => $orderStatus->handle,
                'color' => $orderStatus->color,
                'description' => $orderStatus->description,
                'sortOrder' => (int)($orderStatus->sortOrder ?? 99),
                'default' => (bool)$orderStatus->default,
                'emails' => array_combine($emails, $emails)
            ];
        }

        $configPath = self::CONFIG_STATUSES_KEY . '.' . $statusUid;
        $projectConfig->set($configPath, $configData);

        if ($isNewStatus) {
            $orderStatus->id = Db::idByUid(Table::ORDERSTATUSES, $statusUid);
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
            $statusRecord->description = $data['description'] ?? null;
            $statusRecord->sortOrder = $data['sortOrder'] ?? 99;
            $statusRecord->default = $data['default'];
            $statusRecord->uid = $statusUid;

            // Save the volume
            $statusRecord->save(false);

            if ($statusRecord->default) {
                OrderStatusRecord::updateAll(['default' => false], ['not', ['id' => $statusRecord->id]]);
            }

            $connection = Craft::$app->getDb();
            // Drop them all and we will recreate the new ones.
            $connection->createCommand()->delete(Table::ORDERSTATUS_EMAILS, ['orderStatusId' => $statusRecord->id])->execute();

            if (!empty($data['emails'])) {
                foreach ($data['emails'] as $emailUid) {
                    Craft::$app->projectConfig->processConfigChanges(Emails::CONFIG_EMAILS_KEY . '.' . $emailUid);
                }

                $emailIds = Db::idsByUids(Table::EMAILS, $data['emails']);

                foreach ($emailIds as $emailId) {
                    $connection->createCommand()
                        ->insert(Table::ORDERSTATUS_EMAILS, [
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
     * Delete an order status by it's id.
     *
     * @param int $id
     * @return bool
     * @throws Throwable
     */
    public function deleteOrderStatusById(int $id): bool
    {
        $statuses = $this->getAllOrderStatuses();
        $orderStatus = $this->getOrderStatusById($id);

        // Can only delete if we have one that can remain as the default
        if (count($statuses) < 2 || $orderStatus == null) {
            return false;
        }

        Craft::$app->getProjectConfig()->remove(self::CONFIG_STATUSES_KEY . '.' . $orderStatus->uid);
        return true;
    }


    /**
     * Handle order status being deleted
     *
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable if reasons
     */
    public function handleDeletedOrderStatus(ConfigEvent $event)
    {
        $orderStatusUid = $event->tokenMatches[0];

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $orderStatusRecord = $this->_getOrderStatusRecord($orderStatusUid);

            // Save the volume
            $orderStatusRecord->softDelete();

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
                    Craft::$app->getQueue()->push(new SendEmail([
                        'orderId' => $order->id,
                        'commerceEmailId' => $email->id,
                        'orderHistoryId' => $orderHistory->id,
                        'orderData' => $order->toArray()
                    ]));
                }
            }
        }
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

        $uidsByIds = Db::uidsByIds(Table::ORDERSTATUSES, $ids);

        foreach ($ids as $orderStatus => $statusId) {
            if (!empty($uidsByIds[$statusId])) {
                $statusUid = $uidsByIds[$statusId];
                $projectConfig->set(self::CONFIG_STATUSES_KEY . '.' . $statusUid . '.sortOrder', $orderStatus + 1);
            }
        }

        return true;
    }


    /**
     * Returns a Query object prepped for retrieving order statuses
     *
     * @param bool $withTrashed
     * @return Query
     */
    private function _createOrderStatusesQuery($withTrashed = false): Query
    {
        $query = (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'color',
                'description',
                'sortOrder',
                'default',
                'dateDeleted',
                'uid'
            ])
            ->orderBy('sortOrder')
            ->from([Table::ORDERSTATUSES]);

        // todo: remove schema version condition after next beakpoint
        $schemaVersion = Plugin::getInstance()->schemaVersion;
        if (version_compare($schemaVersion, '2.1.09', '>=')) {
            if (!$withTrashed) {
                $query->where(['dateDeleted' => null]);
            }
        }
        return $query;
    }

    /**
     * Gets an order status' record by uid.
     *
     * @param string $uid
     * @return OrderStatusRecord
     */
    private function _getOrderStatusRecord(string $uid): OrderStatusRecord
    {
        /** @var OrderStatusRecord $orderStatus */
        if ($orderStatus = OrderStatusRecord::findWithTrashed()->where(['uid' => $uid])->one()) {
            return $orderStatus;
        }

        return new OrderStatusRecord();
    }
}
