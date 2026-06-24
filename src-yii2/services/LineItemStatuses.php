<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\events\DefaultLineItemStatusEvent;
use craft\commerce\helpers\ProjectConfigData;
use craft\commerce\models\LineItem;
use craft\commerce\models\LineItemStatus;
use craft\commerce\Plugin;
use craft\commerce\records\LineItemStatus as LineItemStatusRecord;
use craft\db\Query;
use craft\errors\SiteNotFoundException;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use DateTime;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\ServerErrorHttpException;

/**
 * Order status service.
 *
 * @property LineItemStatus|null $defaultLineItemStatus default line item status from the DB
 * @property LineItemStatus[]|array $allLineItemStatuses all Order Statuses
 * @property null|int $defaultLineItemStatusId default line item status ID from the DB
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class LineItemStatuses extends Component
{
    /**
     * @event DefaultLineItemStatusEvent The event that is triggered when getting a default status for a line item.
     * You may set [[DefaultLineItemStatusEvent::lineItemStatus]] to a desired LineItemStatus to override the default status set in control panel.
     *
     * Plugins can get notified when a default line item status is being fetched
     *
     * ```php
     * use craft\commerce\events\DefaultLineItemStatusEvent;
     * use craft\commerce\services\LineItemStatuses;
     * use yii\base\Event;
     *
     * Event::on(LineItemStatuses::class, LineItemStatuses::EVENT_DEFAULT_LINE_ITEM_STATUS, function(DefaultLineItemStatusEvent $e) {
     *     // Perhaps determine a better default line item status than the one set in control panel
     * });
     * ```
     */
    public const EVENT_DEFAULT_LINE_ITEM_STATUS = 'defaultLineItemStatus';

    public const CONFIG_STATUSES_KEY = 'commerce.lineItemStatuses';

    /**
     * @var array|null
     * @since 5.0.0
     */
    private ?array $_allLineItemStatuses = null;

    /**
     * Get line item status by its handle.
     */
    public function getLineItemStatusByHandle(string $handle, ?int $storeId = null): ?LineItemStatus
    {
        return $this->getAllLineItemStatuses($storeId)->firstWhere('handle', $handle);
    }

    /**
     * Get default lineItem status ID from the DB
     *
     * @noinspection PhpUnused
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

        $event = new DefaultLineItemStatusEvent();
        $event->lineItemStatus = $lineItemStatus;
        $event->lineItem = $lineItem;

        $this->trigger(self::EVENT_DEFAULT_LINE_ITEM_STATUS, $event);

        return $event->lineItemStatus;
    }

    /**
     * Save the line item status.
     *
     * @param bool $runValidation should we validate this line item status before saving.
     * @throws Exception
     * @throws ErrorException
     */
    public function saveLineItemStatus(LineItemStatus $lineItemStatus, bool $runValidation = true): bool
    {
        $isNewStatus = !$lineItemStatus->id;

        if ($runValidation && !$lineItemStatus->validate()) {
            Craft::info('Line item status not saved due to validation error.', __METHOD__);

            return false;
        }

        if ($isNewStatus) {
            $statusUid = StringHelper::UUID();
        } else {
            $statusUid = Db::uidById(Table::LINEITEMSTATUSES, $lineItemStatus->id);
        }

        // Make sure no statuses that are not archived share the handle
        // @TODO can this be removed now it is handled by validation?
        $existingStatus = $this->getLineItemStatusByHandle($lineItemStatus->handle, $lineItemStatus->storeId);

        if ($existingStatus && (!$lineItemStatus->id || $lineItemStatus->id !== $existingStatus->id)) {
            $lineItemStatus->addError('handle', Craft::t('commerce', 'That handle is already in use'));
            return false;
        }

        $projectConfig = Craft::$app->getProjectConfig();

        if ($lineItemStatus->isArchived) {
            $configData = null;
        } else {
            $configData = $lineItemStatus->getConfig();
        }

        $configPath = self::CONFIG_STATUSES_KEY . '.' . $statusUid;
        $projectConfig->set($configPath, $configData);

        if ($isNewStatus) {
            $lineItemStatus->id = Db::idByUid(Table::LINEITEMSTATUSES, $statusUid);
        }

        $this->_clearCaches();

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

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $statusRecord = $this->_getLineItemStatusRecord($statusUid);
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

            $statusRecord->save(false);

            if ($statusRecord->default) {
                LineItemStatusRecord::updateAll(['default' => 0], ['and',
                    ['not', ['id' => $statusRecord->id]],
                    ['storeId' => $statusRecord->storeId],
                ]);
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

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $lineItemStatusRecord = $this->_getLineItemStatusRecord($lineItemStatusUid);

            $lineItemStatusRecord->isArchived = true;
            $lineItemStatusRecord->dateArchived = Db::prepareDateForDb(new DateTime());

            // Save the volume
            $lineItemStatusRecord->save(false);

            $transaction->commit();

            $this->_clearCaches();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Returns all Order Statuses
     *
     * @param int|null $storeId
     * @return Collection<LineItemStatus>
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    public function getAllLineItemStatuses(?int $storeId = null): Collection
    {
        $storeId ??= Plugin::getInstance()->getStores()->getCurrentStore()->id;

        if ($this->_allLineItemStatuses === null || !isset($this->_allLineItemStatuses[$storeId])) {
            $results = $this->_createLineItemStatusesQuery()
                ->andWhere(['storeId' => $storeId])
                ->all();

            // Start with a blank slate if it isn't memoized
            if ($this->_allLineItemStatuses === null) {
                $this->_allLineItemStatuses = [];
            }

            foreach ($results as $result) {
                $lineItemStatus = Craft::createObject([
                    'class' => LineItemStatus::class,
                    'attributes' => $result,
                ]);

                if (!isset($this->_allLineItemStatuses[$lineItemStatus->storeId])) {
                    $this->_allLineItemStatuses[$lineItemStatus->storeId] = collect();
                }

                $this->_allLineItemStatuses[$lineItemStatus->storeId]->push($lineItemStatus);
            }
        }

        return $this->_allLineItemStatuses[$storeId] ?? collect();
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
     * @throws Exception
     * @throws ErrorException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function reorderLineItemStatuses(array $ids): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $uidsByIds = Db::uidsByIds(Table::LINEITEMSTATUSES, $ids);

        foreach ($ids as $lineItemStatus => $statusId) {
            if (!empty($uidsByIds[$statusId])) {
                $statusUid = $uidsByIds[$statusId];
                $projectConfig->set(self::CONFIG_STATUSES_KEY . '.' . $statusUid . '.sortOrder', $lineItemStatus + 1);
            }
        }

        $this->_clearCaches();

        return true;
    }

    /**
     * Returns a Query object prepped for retrieving line item statuses
     */
    private function _createLineItemStatusesQuery(): Query
    {
        return (new Query())
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
            ->where(['isArchived' => false])
            ->orderBy('sortOrder')
            ->from([Table::LINEITEMSTATUSES]);
    }

    /**
     * Gets an lineitem status' record by uid.
     */
    private function _getLineItemStatusRecord(string $uid): LineItemStatusRecord
    {
        if ($lineItemStatus = LineItemStatusRecord::findOne(['uid' => $uid])) {
            return $lineItemStatus;
        }

        return new LineItemStatusRecord();
    }

    /**
     * Clear all memoization
     *
     * @since 3.2.5
     */
    public function _clearCaches(): void
    {
        $this->_allLineItemStatuses = null;
    }
}
