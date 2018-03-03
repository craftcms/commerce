<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\OrderSettings as OrderSettingsModel;
use craft\commerce\records\OrderSettings as OrderSettingsRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

/**
 * Order settings service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrderSettings extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var
     */
    private $_orderSettingsById;

    // Public Methods
    // =========================================================================

    /**
     * Get order settings by their ID.
     *
     * @param int $orderSettingsId
     * @return OrderSettingsModel|null
     */
    public function getOrderSettingById($orderSettingsId)
    {
        if (null === $this->_orderSettingsById || !array_key_exists($orderSettingsId, $this->_orderSettingsById)) {
            $result = $this->_createOrderSettingsQuery()
                ->where(['id' => $orderSettingsId])
                ->one();

            if ($result) {
                $orderSetting = new OrderSettingsModel($result);
            } else {
                $orderSetting = null;
            }

            $this->_orderSettingsById[$orderSettingsId] = $orderSetting;
        }

        if (!isset($this->_orderSettingsById[$orderSettingsId])) {
            return null;
        }

        return $this->_orderSettingsById[$orderSettingsId];
    }

    /**
     * Get order settings by handle
     *
     * @param string $handle
     * @return OrderSettingsModel|null
     */
    public function getOrderSettingByHandle($handle)
    {
        $result = $this->_createOrderSettingsQuery()
            ->where(['handle' => $handle])
            ->one();

        if (!$result) {
            return null;
        }

        $orderSetting = new OrderSettingsModel($result);
        $this->_orderSettingsById[$orderSetting->id] = $orderSetting;

        return $orderSetting;
    }

    /**
     * Save order settings.
     *
     * @param OrderSettingsModel $orderSettings
     * @param bool $runValidation should we validate this address before saving.
     * @return bool
     * @throws Exception
     */
    public function saveOrderSetting(OrderSettingsModel $orderSettings, bool $runValidation = true): bool
    {
        if ($orderSettings->id) {
            $orderSettingsRecord = OrderSettingsRecord::findOne($orderSettings->id);

            if (!$orderSettingsRecord) {
                throw new Exception(Craft::t('commerce', 'No order settings exists with the ID “{id}”',
                    ['id' => $orderSettings->id]));
            }
        } else {
            $orderSettingsRecord = new OrderSettingsRecord();
        }

        if ($runValidation && !$orderSettings->validate()) {
            Craft::info('Order Settings not saved due to validation error.', __METHOD__);

            return false;
        }

        $orderSettingsRecord->name = $orderSettings->name;
        $orderSettingsRecord->handle = $orderSettings->handle;

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Save the new one
            $fieldLayout = $orderSettings->getFieldLayout();
            Craft::$app->getFields()->saveLayout($fieldLayout);

            // Update the Order record/model with the new layout ID
            $orderSettings->fieldLayoutId = $fieldLayout->id;
            $orderSettingsRecord->fieldLayoutId = $fieldLayout->id;

            // Save it!
            $orderSettingsRecord->save(false);

            // Now that we have a calendar ID, save it on the model
            if (!$orderSettings->id) {
                $orderSettings->id = $orderSettingsRecord->id;
            }

            // Update service's cache
            $this->_orderSettingsById[$orderSettings->id] = $orderSettings;

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        return true;
    }

    // Private methods
    // =========================================================================
    /**
     * Returns a Query object prepped for retrieving order settings.
     *
     * @return Query
     */
    private function _createOrderSettingsQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'fieldLayoutId',
            ])
            ->from(['{{%commerce_ordersettings}}']);
    }
}
