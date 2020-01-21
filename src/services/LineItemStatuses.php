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
use craft\commerce\models\LineItem;
use craft\commerce\models\LineItemStatus;
use craft\commerce\Plugin;
use craft\commerce\records\LineItemStatus as LineItemStatusRecord;
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
     * You may set [[DefaultLineItemStatusEvent::lineItemStatus]] to a desired LineItemStatus to override the default status set in CP
     *
     * Plugins can get notified when a default line item status is being fetched
     *
     * ```php
     * use craft\commerce\events\DefaultLineItemStatusEvent;
     * use craft\commerce\services\LineItemStatuses;
     * use yii\base\Event;
     *
     * Event::on(LineItemStatuses::class, LineItemStatuses::EVENT_DEFAULT_ORDER_STATUS, function(DefaultLineItemStatusEvent $e) {
     *     // Do something - perhaps figure out a better default line item status than the one set in CP
     * });
     * ```
     */
    const EVENT_DEFAULT_LINE_ITEM_STATUS = 'defaultLineItemStatus';

    const CONFIG_STATUSES_KEY = 'commerce.lineItemStatuses';


    /**
     * @var bool
     */
    private $_fetchedAllStatuses = false;

    /**
     * @var LineItemStatus[]
     */
    private $_lineItemStatusesById = [];

    /**
     * @var LineItemStatus[]
     */
    private $_lineItemStatusesByHandle = [];

    /**
     * @var LineItemStatus
     */
    private $_defaultLineItemStatus;


    /**
     * Get line item status by its handle.
     *
     * @param string $handle
     * @return LineItemStatus|null
     */
    public function getLineItemStatusByHandle($handle)
    {
        if (isset($this->_lineItemStatusesByHandle[$handle])) {
            return $this->_lineItemStatusesByHandle[$handle];
        }

        if ($this->_fetchedAllStatuses) {
            return null;
        }

        $result = $this->_createLineItemStatusesQuery()
            ->where(['handle' => $handle])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeLineItemStatus(new LineItemStatus($result));

        return $this->_lineItemStatusesByHandle[$handle];
    }

    /**
     * Get default lineItem status ID from the DB
     *
     * @return int|null
     */
    public function getDefaultLineItemStatusId()
    {
        $defaultStatus = $this->getDefaultLineItemStatus();

        if ($defaultStatus && $defaultStatus->id) {
            return $defaultStatus->id;
        }

        return null;
    }

    /**
     * Get default lineItem status from the DB
     *
     * @return LineItemStatus|null
     */
    public function getDefaultLineItemStatus()
    {
        if ($this->_defaultLineItemStatus !== null) {
            return $this->_defaultLineItemStatus;
        }

        $result = $this->_createLineItemStatusesQuery()
            ->where(['default' => 1])
            ->one();

        return new LineItemStatus($result);
    }

    /**
     * Get the default lineItem status for a particular lineItem. Defaults to the CP configured default lineItem status.
     *
     * @param LineItem $lineItem
     * @return LineItemStatus|null
     */
    public function getDefaultLineItemStatusForLineItem(LineItem $lineItem)
    {
        $lineItemStatus = $this->getDefaultLineItemStatus();

        $event = new DefaultLineItemStatusEvent();
        $event->lineItemStatus = $lineItemStatus;
        $event->lineItem = $lineItem;

        $this->trigger(self::EVENT_DEFAULT_LINE_ITEM_STATUS, $event);

        return $event->lineItemStatus;
    }

    /**
     * Save the line item status.
     *
     * @param LineItemStatus $lineItemStatus
     * @param bool $runValidation should we validate this line item status before saving.
     * @return bool
     * @throws Exception
     * @throws ErrorException
     */
    public function saveLineItemStatus(LineItemStatus $lineItemStatus, bool $runValidation = true): bool
    {
        $isNewStatus = !(bool)$lineItemStatus->id;

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
        $existingStatus = $this->getLineItemStatusByHandle($lineItemStatus->handle);

        if ($existingStatus && (!$lineItemStatus->id || $lineItemStatus->id !== $existingStatus->id)) {
            $lineItemStatus->addError('handle', Plugin::t('That handle is already in use'));
            return false;
        }

        $projectConfig = Craft::$app->getProjectConfig();

        if ($lineItemStatus->isArchived) {
            $configData = null;
        } else {
            $configData = [
                'name' => $lineItemStatus->name,
                'handle' => $lineItemStatus->handle,
                'color' => $lineItemStatus->color,
                'sortOrder' => (int)($lineItemStatus->sortOrder ?? 99),
                'default' => (bool)$lineItemStatus->default
            ];
        }

        $configPath = self::CONFIG_STATUSES_KEY . '.' . $statusUid;
        $projectConfig->set($configPath, $configData);

        if ($isNewStatus) {
            $lineItemStatus->id = Db::idByUid(Table::LINEITEMSTATUSES, $statusUid);
        }

        return true;
    }

    /**
     * Handle line item status change.
     *
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable if reasons
     */
    public function handleChangedLineItemStatus(ConfigEvent $event)
    {
        $statusUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $statusRecord = $this->_getLineItemStatusRecord($statusUid);

            $statusRecord->name = $data['name'];
            $statusRecord->handle = $data['handle'];
            $statusRecord->color = $data['color'];
            $statusRecord->sortOrder = $data['sortOrder'] ?? 99;
            $statusRecord->default = $data['default'];
            $statusRecord->uid = $statusUid;

            // Save the volume
            $statusRecord->save(false);

            if ($statusRecord->default) {
                LineItemStatusRecord::updateAll(['default' => 0], ['not', ['id' => $statusRecord->id]]);
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
     * @param int $id
     * @return bool
     * @throws Throwable
     */
    public function archiveLineItemStatusById(int $id): bool
    {
        $status = $this->getLineItemStatusById($id);
        if ($status) {
            $status->isArchived = true;
            return $this->saveLineItemStatus($status);
        }
        return false;
    }


    /**
     * Handle line item status being archived
     *
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable if reasons
     */
    public function handleArchivedLineItemStatus(ConfigEvent $event)
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
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Returns all Order Statuses
     *
     * @return LineItemStatus[]
     */
    public function getAllLineItemStatuses(): array
    {
        if (!$this->_fetchedAllStatuses) {
            $results = $this->_createLineItemStatusesQuery()->all();

            foreach ($results as $row) {
                $status = new LineItemStatus($row);
                $status->typecastAttributes();
                $this->_memoizeLineItemStatus($status);
            }

            $this->_fetchedAllStatuses = true;
        }

        return $this->_lineItemStatusesById;
    }

    /**
     * Get an line item status by ID
     *
     * @param int $id
     * @return LineItemStatus|null
     */
    public function getLineItemStatusById($id)
    {
        if (isset($this->_lineItemStatusesById[$id])) {
            return $this->_lineItemStatusesById[$id];
        }

        if ($this->_fetchedAllStatuses) {
            return null;
        }

        $result = $this->_createLineItemStatusesQuery()
            ->where(['id' => $id])
            ->one();

        if (!$result) {
            return null;
        }

        $this->_memoizeLineItemStatus(new LineItemStatus($result));

        return $this->_lineItemStatusesById[$id];
    }

    /**
     * Reorders the line item statuses.
     *
     * @param array $ids
     * @return bool
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

        return true;
    }


    /**
     * Memoize an line item status by its ID and handle.
     *
     * @param LineItemStatus $lineItemStatus
     */
    private function _memoizeLineItemStatus(LineItemStatus $lineItemStatus)
    {
        $this->_lineItemStatusesById[$lineItemStatus->id] = $lineItemStatus;
        $this->_lineItemStatusesByHandle[$lineItemStatus->handle] = $lineItemStatus;
    }

    /**
     * Returns a Query object prepped for retrieving line item statuses
     *
     * @return Query
     */
    private function _createLineItemStatusesQuery(): Query
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
            ->from([Table::LINEITEMSTATUSES]);
    }

    /**
     * Gets an lineitem status' record by uid.
     *
     * @param string $uid
     * @return LineItemStatusRecord
     */
    private function _getLineItemStatusRecord(string $uid): LineItemStatusRecord
    {
        if ($lineItemStatus = LineItemStatusRecord::findOne(['uid' => $uid])) {
            return $lineItemStatus;
        }

        return new LineItemStatusRecord();
    }
}
