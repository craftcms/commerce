<?php

namespace craft\commerce\services;


use Craft;
use craft\commerce\models\OrderSettings as OrderSettingsModel;
use craft\commerce\records\OrderSettings as OrderSettingsRecord;
use craft\db\Query;
use yii\base\Component;

/**
 * Order settings service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class OrderSettings extends Component
{
    /**
     * @var
     */
    private $_orderSettingsById;

    /**
     * @param int $orderSettingsId
     *
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
     * @param string $handle
     *
     * @return OrderSettingsModel|null
     */
    public function getOrderSettingByHandle($handle)
    {
        $result = $this->_createOrderSettingsQuery()
            ->where(['handle' => $handle])
            ->one();

        if ($result) {
            $orderSetting = new OrderSettingsModel($result);
            $this->_orderSettingsById[$orderSetting->id] = $orderSetting;

            return $orderSetting;
        }

        return null;
    }

    /**
     * @param OrderSettingsModel $orderSettings
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveOrderSetting(OrderSettingsModel $orderSettings)
    {
        if ($orderSettings->id) {
            $orderSettingsRecord = OrderSettingsRecord::findOne($orderSettings->id);

            if (!$orderSettingsRecord) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No order settings exists with the ID “{id}”',
                    ['id' => $orderSettings->id]));
            }
        } else {
            $orderSettingsRecord = new OrderSettingsRecord();
        }

        $orderSettingsRecord->name = $orderSettings->name;
        $orderSettingsRecord->handle = $orderSettings->handle;

        $orderSettingsRecord->validate();
        $orderSettings->addErrors($orderSettingsRecord->getErrors());

        if (!$orderSettings->hasErrors()) {

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

        return false;
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
