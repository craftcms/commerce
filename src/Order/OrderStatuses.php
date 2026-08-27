<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order;

use craft\commerce\Plugin;
use craft\events\ConfigEvent;
use craft\helpers\Db as CraftDb;
use CraftCms\Cms\Database\Table as CraftTable;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Email\Events\EmailEvent;
use CraftCms\Commerce\Email\Jobs\SendEmailJob;
use CraftCms\Commerce\Helpers\Locale;
use CraftCms\Commerce\Helpers\ProjectConfigData;
use CraftCms\Commerce\Order\Data\OrderHistory;
use CraftCms\Commerce\Order\Data\OrderStatus;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Events\DefaultOrderStatusEvent;
use CraftCms\Commerce\Order\Events\OrderStatusEmailsEvent;
use CraftCms\Commerce\Order\Models\OrderStatus as OrderStatusRecord;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

#[Singleton]
class OrderStatuses
{
    public const string EVENT_DEFAULT_ORDER_STATUS = 'defaultOrderStatus';

    public const string EVENT_ORDER_STATUS_CHANGE_EMAILS = 'orderStatusChangeEmails';

    public const string CONFIG_STATUSES_KEY = 'commerce.orderStatuses';

    /**
     * @var array<int, Collection<int, OrderStatus>>|null
     */
    private ?array $allOrderStatuses = null;

    /**
     * Returns all Order Statuses
     *
     * @return Collection<int, OrderStatus>
     */
    public function getAllOrderStatuses(?int $storeId = null, bool $withTrashed = false): Collection
    {
        $storeId ??= app(Stores::class)->getCurrentStore()->id;

        if ($this->allOrderStatuses === null || !isset($this->allOrderStatuses[$storeId])) {
            $results = $this->query(true)->where('storeId', $storeId)->get();

            $this->allOrderStatuses ??= [];

            foreach ($results as $result) {
                $orderStatus = new OrderStatus((array)$result);

                $this->allOrderStatuses[$orderStatus->storeId] ??= collect();
                $this->allOrderStatuses[$orderStatus->storeId]->push($orderStatus);
            }
        }

        if (!isset($this->allOrderStatuses[$storeId])) {
            return collect();
        }

        return $this->allOrderStatuses[$storeId]->filter(fn(OrderStatus $os) => (!$withTrashed && $os->dateDeleted === null) || $withTrashed);
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

        $event = new DefaultOrderStatusEvent(
            orderStatus: $orderStatus,
            order: $order,
        );

        // TODO: migrate event firing to Laravel once event system is bridged
        if (Plugin::getInstance()->getOrderStatuses()->hasEventHandlers(self::EVENT_DEFAULT_ORDER_STATUS)) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getOrderStatuses()->trigger(self::EVENT_DEFAULT_ORDER_STATUS, $event);
        }

        return $event->orderStatus;
    }

    public function getOrderCountByStatus(?int $storeId = null): array
    {
        $storeId ??= app(Stores::class)->getCurrentStore()->id;

        $countGroupedByStatusId = DB::table(Table::ORDERS . ' as o')
            ->select(['o.orderStatusId', DB::raw('count(o.id) as orderCount')])
            ->join(CraftTable::ELEMENTS . ' as e', 'o.id', '=', 'e.id')
            ->where('o.isCompleted', true)
            ->whereNull('e.dateDeleted')
            ->where('o.storeId', $storeId)
            ->groupBy('o.orderStatusId')
            ->get()
            ->keyBy('orderStatusId')
            ->map(fn($row) => (array)$row)
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
     */
    public function saveOrderStatus(OrderStatus $orderStatus, array $emailIds = [], bool $runValidation = true, bool $force = false): bool
    {
        $isNewStatus = !(bool)$orderStatus->id;

        if ($runValidation && !$orderStatus->validate()) {
            Log::info('Order status not saved due to validation error.');

            return false;
        }

        if ($isNewStatus) {
            $statusUid = Str::uuid()->toString();
        } else {
            $statusUid = CraftDb::uidById(Table::ORDERSTATUSES, $orderStatus->id);
        }

        $otherStatuses = $this->getAllOrderStatuses($orderStatus->storeId)->where('uid', '!=', $statusUid)->all();

        // if this is the only order status, set it as the default
        $orderStatus->default = empty($otherStatuses) ? true : $orderStatus->default;

        $configData = $orderStatus->dateDeleted ? null : $orderStatus->getConfig($emailIds);

        $configPath = self::CONFIG_STATUSES_KEY . '.' . $statusUid;
        ProjectConfig::set($configPath, $configData, force: $force);

        if ($isNewStatus) {
            $orderStatus->id = CraftDb::idByUid(Table::ORDERSTATUSES, $statusUid);
            $orderStatus->uid = $statusUid;
        }

        $this->allOrderStatuses = null;

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
     * @throws Throwable if reasons
     */
    public function handleChangedOrderStatus(ConfigEvent $event): void
    {
        ProjectConfigData::ensureAllStoresProcessed();

        $statusUid = $event->tokenMatches[0];
        $data = $event->newValue;

        DB::beginTransaction();
        try {
            $statusRecord = $this->getOrderStatusRecord($statusUid);

            // Get store by uid and convert `$data['store']` to `storeId`
            $store = app(Stores::class)->getStoreByUid($data['store']);

            $statusRecord->name = $data['name'];
            $statusRecord->storeId = $store->id;
            $statusRecord->handle = $data['handle'];
            $statusRecord->color = $data['color'];
            $statusRecord->description = $data['description'] ?? null;
            $statusRecord->sortOrder = $data['sortOrder'] ?? 99;
            $statusRecord->default = $data['default'];
            $statusRecord->uid = $statusUid;

            // Save the status
            if ($statusRecord->dateDeleted) {
                $statusRecord->restore();
            } else {
                $statusRecord->save();
            }

            // Drop them all and we will recreate the new ones.
            DB::table(Table::ORDERSTATUS_EMAILS)->where('orderStatusId', $statusRecord->id)->delete();

            if (!empty($data['emails'])) {
                foreach ($data['emails'] as $emailUid) {
                    ProjectConfig::processConfigChanges(Emails::CONFIG_EMAILS_KEY . '.' . $emailUid);
                }

                $emailIds = CraftDb::idsByUids(Table::EMAILS, $data['emails']);
                $now = now()->toDateTimeString();

                foreach ($emailIds as $emailId) {
                    DB::table(Table::ORDERSTATUS_EMAILS)->insert([
                        'orderStatusId' => $statusRecord->id,
                        'emailId' => $emailId,
                        'dateCreated' => $now,
                        'dateUpdated' => $now,
                    ]);
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
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
        if (count($statuses) < 2 || $orderStatus === null) {
            return false;
        }

        // Prevent deletion of order status if there are orders with this status
        $orderCounts = $this->getOrderCountByStatus($storeId);
        if (!isset($orderCounts[$id]) || $orderCounts[$id]['orderCount'] > 0) {
            return false;
        }

        ProjectConfig::remove(self::CONFIG_STATUSES_KEY . '.' . $orderStatus->uid);
        return true;
    }

    /**
     * Handle order status being deleted
     *
     * @throws Throwable if reasons
     */
    public function handleDeletedOrderStatus(ConfigEvent $event): void
    {
        $orderStatusUid = $event->tokenMatches[0];

        DB::beginTransaction();
        try {
            $orderStatusRecord = $this->getOrderStatusRecord($orderStatusUid);

            $orderStatusRecord->delete();

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Clear caches
        $this->allOrderStatuses = null;
    }

    /**
     * Prune a deleted email from order statuses.
     */
    public function pruneDeletedEmail(EmailEvent $event): void
    {
        $emailUid = $event->email->uid;

        $statuses = ProjectConfig::get(self::CONFIG_STATUSES_KEY);

        // Loop through the volumes and prune the UID from field layouts.
        if (is_array($statuses)) {
            foreach ($statuses as $orderStatusUid => $orderStatus) {
                ProjectConfig::remove(self::CONFIG_STATUSES_KEY . '.' . $orderStatusUid . '.emails.' . $emailUid);
            }
        }
    }

    /**
     * Handler for order status change event
     */
    public function statusChangeHandler(Order $order, OrderHistory $orderHistory): void
    {
        $status = $this->getOrderStatusById($order->orderStatusId, $order->storeId);

        if ($status === null) {
            return;
        }

        // Raising 'beforeOrderStatusChange' event
        $event = new OrderStatusEmailsEvent(
            orderHistory: $orderHistory,
            order: $order,
            emails: $status->getEmails(),
        );
        $event->isValid = !$order->suppressEmails;

        // TODO: migrate event firing to Laravel once event system is bridged
        if (Plugin::getInstance()->getOrderStatuses()->hasEventHandlers(self::EVENT_ORDER_STATUS_CHANGE_EMAILS)) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getOrderStatuses()->trigger(self::EVENT_ORDER_STATUS_CHANGE_EMAILS, $event);
        }

        if (!$event->isValid || empty($event->emails)) {
            // Don't send emails
            return;
        }

        $originalLanguage = \Craft::$app->language;
        $originalFormattingLocale = \Craft::$app->formattingLocale;

        foreach ($event->emails as $email) {
            if (!$email->enabled) {
                continue;
            }

            // Set language by email's set locale
            // We need to do this here since $order->toArray() uses the locale to format asCurrency attributes
            $language = $email->getRenderLanguage($event->order);
            Locale::switchAppLanguage($language);

            SendEmailJob::dispatch(
                orderId: $event->order->id,
                orderData: $event->order->toArray(),
                commerceEmailId: $email->id,
                orderHistoryId: $event->orderHistory->id,
            );
        }

        // Set previous language back
        Locale::switchAppLanguage($originalLanguage, $originalFormattingLocale->id);
    }

    /**
     * Reorders the order statuses.
     *
     * @param int[] $ids
     */
    public function reorderOrderStatuses(array $ids): bool
    {
        $uidsByIds = CraftDb::uidsByIds(Table::ORDERSTATUSES, $ids);

        foreach ($ids as $orderStatus => $statusId) {
            if (!empty($uidsByIds[$statusId])) {
                $statusUid = $uidsByIds[$statusId];
                ProjectConfig::set(self::CONFIG_STATUSES_KEY . '.' . $statusUid . '.sortOrder', $orderStatus + 1);
            }
        }

        return true;
    }

    private function query(bool $withTrashed = false): Builder
    {
        $query = DB::table(Table::ORDERSTATUSES)
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
            ->orderBy('sortOrder');

        if (!$withTrashed) {
            $query->whereNull('dateDeleted');
        }

        return $query;
    }

    /**
     * Gets an order status' record by uid.
     */
    private function getOrderStatusRecord(string $uid): OrderStatusRecord
    {
        return OrderStatusRecord::withTrashed()->where('uid', $uid)->first() ?? new OrderStatusRecord();
    }
}
