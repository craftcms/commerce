<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order;

use craft\commerce\Plugin;
use craft\events\ConfigEvent;
use craft\helpers\Db as CraftDb;
use CraftCms\Cms\Support\Facades\ProjectConfig;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\ProjectConfigData;
use CraftCms\Commerce\Order\Events\DefaultLineItemStatusEvent;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\Models\LineItemStatus;
use CraftCms\Commerce\Order\Records\LineItemStatus as LineItemStatusRecord;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use function CraftCms\Cms\t;

#[Singleton]
class LineItemStatuses
{
    public const string EVENT_DEFAULT_LINE_ITEM_STATUS = 'defaultLineItemStatus';

    public const string CONFIG_STATUSES_KEY = 'commerce.lineItemStatuses';

    /**
     * @var array<int, Collection<int, LineItemStatus>>|null
     */
    private ?array $allLineItemStatuses = null;

    /**
     * Get line item status by its handle.
     */
    public function getLineItemStatusByHandle(string $handle, ?int $storeId = null): ?LineItemStatus
    {
        return $this->getAllLineItemStatuses($storeId)->firstWhere('handle', $handle);
    }

    /**
     * Get default lineItem status ID from the DB
     */
    public function getDefaultLineItemStatusId(?int $storeId = null): ?int
    {
        return $this->getDefaultLineItemStatus($storeId)?->id;
    }

    /**
     * Get default lineItem status from the DB
     */
    public function getDefaultLineItemStatus(?int $storeId = null): ?LineItemStatus
    {
        return $this->getAllLineItemStatuses($storeId)->firstWhere('default', true);
    }

    /**
     * Get the default lineItem status for a particular lineItem. Defaults to the default lineItem status as configured
     * in the control panel.
     */
    public function getDefaultLineItemStatusForLineItem(LineItem $lineItem): ?LineItemStatus
    {
        if (!$order = $lineItem->getOrder()) {
            return null;
        }

        $lineItemStatus = $this->getDefaultLineItemStatus($order->getStore()->id);

        $event = new DefaultLineItemStatusEvent(
            lineItem: $lineItem,
            lineItemStatus: $lineItemStatus,
        );

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        Plugin::getInstance()->getLineItemStatuses()->trigger(self::EVENT_DEFAULT_LINE_ITEM_STATUS, $event);

        return $event->lineItemStatus;
    }

    /**
     * Save the line item status.
     */
    public function saveLineItemStatus(LineItemStatus $lineItemStatus, bool $runValidation = true): bool
    {
        $isNewStatus = !$lineItemStatus->id;

        if ($runValidation && !$lineItemStatus->validate()) {
            Log::info('Line item status not saved due to validation error.');

            return false;
        }

        if ($isNewStatus) {
            $statusUid = Str::uuid()->toString();
        } else {
            $statusUid = CraftDb::uidById(Table::LINEITEMSTATUSES, $lineItemStatus->id);
        }

        // Make sure no statuses that are not archived share the handle
        $existingStatus = $this->getLineItemStatusByHandle($lineItemStatus->handle, $lineItemStatus->storeId);

        if ($existingStatus && (!$lineItemStatus->id || $lineItemStatus->id !== $existingStatus->id)) {
            $lineItemStatus->addError('handle', t('That handle is already in use', category: 'commerce'));
            return false;
        }

        $configData = $lineItemStatus->isArchived ? null : $lineItemStatus->getConfig();

        $configPath = self::CONFIG_STATUSES_KEY . '.' . $statusUid;
        ProjectConfig::set($configPath, $configData);

        if ($isNewStatus) {
            $lineItemStatus->id = CraftDb::idByUid(Table::LINEITEMSTATUSES, $statusUid);
        }

        $this->clearCaches();

        return true;
    }

    /**
     * Handle line item status change.
     *
     * @throws Throwable if reasons
     */
    public function handleChangedLineItemStatus(ConfigEvent $event): void
    {
        ProjectConfigData::ensureAllStoresProcessed();

        $statusUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = \Craft::$app->getDb()->beginTransaction();
        try {
            $statusRecord = $this->getLineItemStatusRecord($statusUid);
            $store = Plugin::getInstance()->getStores()->getStoreByUid($data['store']);

            $statusRecord->storeId = $store->id;
            $statusRecord->name = $data['name'];
            $statusRecord->handle = $data['handle'];
            $statusRecord->color = $data['color'];
            $statusRecord->sortOrder = $data['sortOrder'] ?? 99;
            $statusRecord->default = $data['default'];
            $statusRecord->uid = $statusUid;
            $statusRecord->isArchived = false;
            $statusRecord->dateArchived = null;

            $statusRecord->save();

            if ($statusRecord->default) {
                LineItemStatusRecord::where('id', '!=', $statusRecord->id)
                    ->where('storeId', $statusRecord->storeId)
                    ->update(['default' => false]);
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Archive an line item status by it's id.
     *
     * @throws Throwable
     */
    public function archiveLineItemStatusById(int $id, ?int $storeId = null): bool
    {
        $status = $this->getLineItemStatusById($id, $storeId);
        if ($status) {
            $status->isArchived = true;
            return $this->saveLineItemStatus($status);
        }
        return false;
    }

    /**
     * Handle line item status being archived
     *
     * @throws Throwable if reasons
     */
    public function handleArchivedLineItemStatus(ConfigEvent $event): void
    {
        $lineItemStatusUid = $event->tokenMatches[0];

        $transaction = \Craft::$app->getDb()->beginTransaction();
        try {
            $lineItemStatusRecord = $this->getLineItemStatusRecord($lineItemStatusUid);

            $lineItemStatusRecord->isArchived = true;
            $lineItemStatusRecord->dateArchived = CraftDb::prepareDateForDb(new \DateTime());

            $lineItemStatusRecord->save();

            $transaction->commit();

            $this->clearCaches();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Returns all Order Statuses
     *
     * @return Collection<int, LineItemStatus>
     */
    public function getAllLineItemStatuses(?int $storeId = null): Collection
    {
        $storeId ??= Plugin::getInstance()->getStores()->getCurrentStore()->id;

        if ($this->allLineItemStatuses === null || !isset($this->allLineItemStatuses[$storeId])) {
            $results = $this->query()->where('storeId', $storeId)->get();

            // Start with a blank slate if it isn't memoized
            $this->allLineItemStatuses ??= [];

            foreach ($results as $result) {
                $lineItemStatus = new LineItemStatus((array)$result);

                $this->allLineItemStatuses[$lineItemStatus->storeId] ??= collect();
                $this->allLineItemStatuses[$lineItemStatus->storeId]->push($lineItemStatus);
            }
        }

        return $this->allLineItemStatuses[$storeId] ?? collect();
    }

    /**
     * Get a line item status by ID
     */
    public function getLineItemStatusById(int $id, ?int $storeId = null): ?LineItemStatus
    {
        return $this->getAllLineItemStatuses($storeId)->firstWhere('id', $id);
    }

    /**
     * Reorders the line item statuses.
     *
     * @param int[] $ids
     */
    public function reorderLineItemStatuses(array $ids): bool
    {
        $uidsByIds = CraftDb::uidsByIds(Table::LINEITEMSTATUSES, $ids);

        foreach ($ids as $lineItemStatus => $statusId) {
            if (!empty($uidsByIds[$statusId])) {
                $statusUid = $uidsByIds[$statusId];
                ProjectConfig::set(self::CONFIG_STATUSES_KEY . '.' . $statusUid . '.sortOrder', $lineItemStatus + 1);
            }
        }

        $this->clearCaches();

        return true;
    }

    private function query(): Builder
    {
        return DB::table(Table::LINEITEMSTATUSES)
            ->select([
                'color',
                'default',
                'handle',
                'id',
                'name',
                'sortOrder',
                'storeId',
                'uid',
            ])
            ->where('isArchived', false)
            ->orderBy('sortOrder');
    }

    /**
     * Gets an lineitem status' record by uid.
     */
    private function getLineItemStatusRecord(string $uid): LineItemStatusRecord
    {
        if ($lineItemStatus = LineItemStatusRecord::where('uid', $uid)->first()) {
            return $lineItemStatus;
        }

        return new LineItemStatusRecord();
    }

    /**
     * Clear all memoization
     */
    public function clearCaches(): void
    {
        $this->allLineItemStatuses = null;
    }
}
