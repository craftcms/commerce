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
use craft\commerce\events\OrderStatusEmailsEvent;
use craft\commerce\helpers\Locale;
use craft\commerce\helpers\ProjectConfigData;
use craft\commerce\models\OrderHistory;
use craft\commerce\models\OrderStatus;
use craft\commerce\Plugin;
use craft\commerce\queue\jobs\SendEmail;
use craft\commerce\records\OrderStatus as OrderStatusRecord;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\errors\SiteNotFoundException;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\Queue;
use craft\helpers\StringHelper;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\ServerErrorHttpException;
use function count;

/**
 * Order status service.
 *
 * @property OrderStatus|null $defaultOrderStatus default order status from the DB
 * @property OrderStatus[]|array $allOrderStatuses all Order Statuses
 * @property-read array $orderCountByStatus
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
    public const EVENT_DEFAULT_ORDER_STATUS = 'defaultOrderStatus';

    /**
     * @event OrderStatusEmailsEvent The email event that is triggered when an order status is changed.
     *
     * Plugins can get notified when an order status is changed
     *
     * ```php
     * use craft\commerce\events\OrderStatusEmailsEvent;
     * use craft\commerce\services\OrderStatuses;
     * use craft\commerce\models\OrderHistory;
     * use craft\commerce\elements\Order;
     * use yii\base\Event;
     *
     * Event::on(
     *     OrderStatuses::class,
     *     OrderStatuses::EVENT_ORDER_STATUS_CHANGE_EMAILS,
     *     function(OrderStatusEmailsEvent $event) {
     *         // @var OrderHistory $orderHistory
     *         $orderHistory = $event->orderHistory;
     *         // @var Order $order
     *         $order = $event->order;
     *
     *         // Let the delivery department know the order’s ready to be delivered
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_ORDER_STATUS_CHANGE_EMAILS = 'orderStatusChangeEmails';

    public const CONFIG_STATUSES_KEY = 'commerce.orderStatuses';

    /**
     * @var Collection[]|null
     */
    private ?array $_allOrderStatuses = null;

    /**
     * Returns all Order Statuses
     *
     * @param int|null $storeId
     * @param bool $withTrashed
     * @return Collection<OrderStatus>
     * @throws InvalidConfigException
     * @throws SiteNotFoundException
     * @since 2.2
     */
    public function getAllOrderStatuses(?int $storeId = null, bool $withTrashed = false): Collection
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        if ($this->_allOrderStatuses === null || !isset($this->_allOrderStatuses[$storeId])) {
            $results = $this->_createOrderStatusesQuery(true)
                ->where(['storeId' => $storeId])
                ->all();

            if ($this->_allOrderStatuses === null) {
                $this->_allOrderStatuses = [];
            }

            foreach ($results as $result) {
                $orderStatus = Craft::createObject([
                    'class' => OrderStatus::class,
                    'attributes' => $result,
                ]);

                if (!isset($this->_allOrderStatuses[$orderStatus->storeId])) {
                    $this->_allOrderStatuses[$orderStatus->storeId] = collect();
                }

                $this->_allOrderStatuses[$orderStatus->storeId]->push($orderStatus);
            }
        }

        if (!isset($this->_allOrderStatuses[$storeId])) {
            return collect();
        }

        return $this->_allOrderStatuses[$storeId]->filter(fn(OrderStatus $os) => (!$withTrashed && $os->dateDeleted === null) || $withTrashed);
    }

    /**
     * Get an order status by ID
     */
    public function getOrderStatusById(int $id, ?int $storeId = null): ?OrderStatus
    {
        return $this->getAllOrderStatuses($storeId)->firstWhere('id', $id);
    }

    /**
     * Get an order status by ID
     */
    public function getOrderStatusByUid(string $uid, ?int $storeId = null): ?OrderStatus
    {
        return $this->getAllOrderStatuses($storeId)->firstWhere('uid', $uid);
    }

    /**
     * Get order status by its handle.
     */
    public function getOrderStatusByHandle(string $handle, ?int $storeId = null): ?OrderStatus
    {
        return $this->getAllOrderStatuses($storeId)->firstWhere('handle', $handle);
    }

    /**
     * Get default order status from the DB
     */
    public function getDefaultOrderStatus(?int $storeId = null): ?OrderStatus
    {
        return $this->getAllOrderStatuses($storeId)->firstWhere('default', true);
    }

    /**
     * Get default order status ID from the DB
     *
     * @noinspection PhpUnused
     */
    public function getDefaultOrderStatusId(?int $storeId = null): ?int
    {
        return $this->getDefaultOrderStatus($storeId)?->id;
    }

    /**
     * Get the default order status for a particular order. Defaults to the control-panel-configured default order status.
     */
    public function getDefaultOrderStatusForOrder(Order $order): ?OrderStatus
    {
        $orderStatus = $this->getDefaultOrderStatus($order->storeId);

        $event = new DefaultOrderStatusEvent([
            'orderStatus' => $orderStatus,
            'order' => $order,
        ]);

        if ($this->hasEventHandlers(self::EVENT_DEFAULT_ORDER_STATUS)) {
            $this->trigger(self::EVENT_DEFAULT_ORDER_STATUS, $event);
        }

        return $event->orderStatus;
    }

    /**
     * @since 3.0.11
     */
    public function getOrderCountByStatus(?int $storeId = null): array
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        $countGroupedByStatusId = (new Query())
            ->select(['[[o.orderStatusId]]', 'count(o.id) as orderCount'])
            ->where([
                '[[o.isCompleted]]' => true,
                '[[e.dateDeleted]]' => null,
                '[[o.storeId]]' => $storeId,
            ])
            ->from([Table::ORDERS . ' o'])
            ->innerJoin([CraftTable::ELEMENTS . ' e'], '[[o.id]] = [[e.id]]')
            ->groupBy(['[[o.orderStatusId]]'])
            ->indexBy('orderStatusId')
            ->all();

        // For those not in the groupBy
        $allStatuses = $this->getAllOrderStatuses($storeId);
        foreach ($allStatuses as $status) {
            if (!isset($countGroupedByStatusId[$status->id])) {
                $countGroupedByStatusId[$status->id] = [
                    'orderStatusId' => $status->id,
                    'handle' => $status->handle,
                    'orderCount' => 0,
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
     * @param bool $runValidation should we validate this order status before saving.
     * @throws Exception
     */
    public function saveOrderStatus(OrderStatus $orderStatus, array $emailIds = [], bool $runValidation = true, $force = false): bool
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

        $otherStatuses = $this->getAllOrderStatuses($orderStatus->storeId)->where('uid', '!=', $statusUid)->all();

        // if this is the only order status, set it as the default
        $orderStatus->default = empty($otherStatuses) ? true : $orderStatus->default;

        $projectConfig = Craft::$app->getProjectConfig();

        if ($orderStatus->dateDeleted) {
            $configData = null;
        } else {
            $configData = $orderStatus->getConfig($emailIds);
        }

        $configPath = self::CONFIG_STATUSES_KEY . '.' . $statusUid;
        $projectConfig->set($configPath, $configData, force: $force);

        if ($isNewStatus) {
            $orderStatus->id = Db::idByUid(Table::ORDERSTATUSES, $statusUid);
            $orderStatus->uid = $statusUid;
        }

        $this->_allOrderStatuses = null;

        // Make sure this is the only default
        if ($orderStatus->default) {
            foreach ($otherStatuses as $otherStatus) {
                $otherStatus->default = false;
                $this->saveOrderStatus($otherStatus, $otherStatus->getEmailIds(), false, true);
            }
        }

        return true;
    }

    /**
     * Handle order status change.
     *
     * @return void
     * @throws Throwable if reasons
     */
    public function handleChangedOrderStatus(ConfigEvent $event)
    {
        ProjectConfigData::ensureAllStoresProcessed();

        $statusUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $statusRecord = $this->_getOrderStatusRecord($statusUid);

            // Get store by uid and convert `$data['store']` to `storeId`
            $store = Plugin::getInstance()->getStores()->getStoreByUid($data['store']);

            $statusRecord->name = $data['name'];
            $statusRecord->storeId = $store->id;
            $statusRecord->handle = $data['handle'];
            $statusRecord->color = $data['color'];
            $statusRecord->description = $data['description'] ?? null;
            $statusRecord->sortOrder = $data['sortOrder'] ?? 99;
            $statusRecord->default = $data['default'];
            $statusRecord->uid = $statusUid;

            // Save the status
            if ($wasTrashed = (bool)$statusRecord->dateDeleted) {
                $statusRecord->restore();
            } else {
                $statusRecord->save(false);
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
                            'emailId' => $emailId,
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
     * @throws Throwable
     */
    public function deleteOrderStatusById(int $id, ?int $storeId = null): bool
    {
        $statuses = $this->getAllOrderStatuses($storeId);
        $orderStatus = $this->getOrderStatusById($id, $storeId);

        // Can only delete if we have one that can remain as the default
        if (count($statuses) < 2 || $orderStatus == null) {
            return false;
        }

        // Prevent deletion of order status if there are orders with this status
        $orderCounts = $this->getOrderCountByStatus($storeId);
        if (!isset($orderCounts[$id]) || $orderCounts[$id]['orderCount'] > 0) {
            return false;
        }

        Craft::$app->getProjectConfig()->remove(self::CONFIG_STATUSES_KEY . '.' . $orderStatus->uid);
        return true;
    }


    /**
     * Handle order status being deleted
     *
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

        // Clear caches
        $this->_allOrderStatuses = null;
    }

    /**
     * Prune a deleted email from order statuses.
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
     * @throws InvalidConfigException
     */
    public function statusChangeHandler(Order $order, OrderHistory $orderHistory): void
    {
        $status = $this->getOrderStatusById($order->orderStatusId);

        if ($status === null) {
            return;
        }

        // Raising 'beforeOrderStatusChange' event
        $event = new OrderStatusEmailsEvent([
            'orderHistory' => $orderHistory,
            'order' => $order,
            'emails' => $status->getEmails(),
            'isValid' => !$order->suppressEmails,
        ]);

        if ($this->hasEventHandlers(self::EVENT_ORDER_STATUS_CHANGE_EMAILS)) {
            $this->trigger(self::EVENT_ORDER_STATUS_CHANGE_EMAILS, $event);
        }

        if (!$event->isValid || empty($event->emails)) {
            // Don't send emails
            return;
        }

        $originalLanguage = Craft::$app->language;
        $originalFormattingLocale = Craft::$app->formattingLocale;

        foreach ($event->emails as $email) {
            if (!$email->enabled) {
                continue;
            }

            // Set language by email's set locale
            // We need to do this here since $order->toArray() uses the locale to format asCurrency attributes
            $language = $email->getRenderLanguage($event->order);
            Locale::switchAppLanguage($language);

            Queue::push(new SendEmail([
                'orderId' => $event->order->id,
                'commerceEmailId' => $email->id,
                'orderHistoryId' => $event->orderHistory->id,
                'orderData' => $event->order->toArray(),
            ]), 100);
        }

        // Set previous language back
        Locale::switchAppLanguage($originalLanguage, $originalFormattingLocale);
    }

    /**
     * Reorders the order statuses.
     *
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
    private function _createOrderStatusesQuery(bool $withTrashed = false): Query
    {
        $query = (new Query())
            ->select([
                'color',
                'dateDeleted',
                'default',
                'description',
                'handle',
                'id',
                'name',
                'sortOrder',
                'storeId',
                'uid',
            ])
            ->orderBy('sortOrder')
            ->from([Table::ORDERSTATUSES]);

        if (!$withTrashed) {
            $query->where(['dateDeleted' => null]);
        }

        return $query;
    }

    /**
     * Gets an order status' record by uid.
     */
    private function _getOrderStatusRecord(string $uid): OrderStatusRecord
    {
        /** @var ?OrderStatusRecord $orderStatus */
        $orderStatus = OrderStatusRecord::findWithTrashed()->where(['uid' => $uid])->one();
        return $orderStatus ?: new OrderStatusRecord();
    }
}
