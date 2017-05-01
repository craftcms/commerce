<?php
namespace Craft;


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
class Commerce_OrderSettingsService extends BaseApplicationComponent
{
    /**
     * @var
     */
    private $_orderSettingsById;

    /**
     * @param int $orderSettingsId
     *
     * @return Commerce_OrderSettingsModel|null
     */
    public function getOrderSettingById($orderSettingsId)
    {
        if (!isset($this->_orderSettingsById) || !array_key_exists($orderSettingsId, $this->_orderSettingsById))
        {
            $result = Commerce_OrderSettingsRecord::model()->findById($orderSettingsId);

            if ($result) {
                $orderSetting = Commerce_OrderSettingsModel::populateModel($result);
            }
            else
            {
                $orderSetting = null;
            }

            $this->_orderSettingsById[$orderSettingsId] = $orderSetting;
        }

        if (isset($this->_orderSettingsById[$orderSettingsId]))
        {
            return $this->_orderSettingsById[$orderSettingsId];
        }
    }

    /**
     * @param string $handle
     *
     * @return Commerce_OrderSettingsModel|null
     */
    public function getOrderSettingByHandle($handle)
    {
        $result = Commerce_OrderSettingsRecord::model()->findByAttributes(['handle' => $handle]);

        if ($result)
        {
            $orderSetting = Commerce_OrderSettingsModel::populateModel($result);
            $this->_orderSettingsById[$orderSetting->id] = $orderSetting;

            return $orderSetting;
        }

        return null;
    }

    /**
     * @param Commerce_OrderSettingsModel $orderSettings
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveOrderSetting(Commerce_OrderSettingsModel $orderSettings)
    {
        if ($orderSettings->id) {
            $orderSettingsRecord = Commerce_OrderSettingsRecord::model()->findById($orderSettings->id);
            if (!$orderSettingsRecord) {
                throw new Exception(Craft::t('No order settings exists with the ID “{id}”',
                    ['id' => $orderSettings->id]));
            }

            $oldOrderSettings = Commerce_OrderSettingsModel::populateModel($orderSettingsRecord);
            $isNewOrderSettings = false;
        } else {
            $orderSettingsRecord = new Commerce_OrderSettingsRecord();
            $isNewOrderSettings = true;
        }

        $orderSettingsRecord->name = $orderSettings->name;
        $orderSettingsRecord->handle = $orderSettings->handle;

        $orderSettingsRecord->validate();
        $orderSettings->addErrors($orderSettingsRecord->getErrors());

        if (!$orderSettings->hasErrors()) {
            $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
            try {
                if (!$isNewOrderSettings && $oldOrderSettings->fieldLayoutId) {
                    // Drop the old field layout
                    craft()->fields->deleteLayoutById($oldOrderSettings->fieldLayoutId);
                }

                // Save the new one
                $fieldLayout = $orderSettings->getFieldLayout();
                craft()->fields->saveLayout($fieldLayout);

                // Update the calendar record/model with the new layout ID
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

                if ($transaction !== null)
                {
                    $transaction->commit();
                }
            } catch (\Exception $e) {
                if ($transaction !== null)
                {
                    $transaction->rollback();
                }
                throw $e;
            }

            return true;
        } else {
            return false;
        }
    }

}
