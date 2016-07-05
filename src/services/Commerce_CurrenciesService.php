<?php
namespace Craft;

/**
 * Currency service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_CurrenciesService extends BaseApplicationComponent
{
    /**
     * @param int $id
     *
     * @return Commerce_CurrencyModel|null
     */
    public function getCurrencyById($id)
    {
        $result = Commerce_CurrencyRecord::model()->findById($id);

        if ($result) {
            return Commerce_CurrencyModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param array $attr
     *
     * @return Commerce_CurrencyModel|null
     */
    public function getCurrencyByAttributes(array $attr)
    {
        $result = Commerce_CurrencyRecord::model()->findByAttributes($attr);

        if ($result) {
            return Commerce_CurrencyModel::populateModel($result);
        }

        return null;
    }

    /**
     * @return Commerce_CurrencyModel[]
     */
    public function getAllCurrencies()
    {
        $records = Commerce_CurrencyRecord::model()->findAll(['order' => 'name']);

        return Commerce_CurrencyModel::populateModels($records);
    }

    /**
     * @param Commerce_CurrencyModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveCurrency(Commerce_CurrencyModel $model)
    {
        if ($model->id) {
            $record = Commerce_CurrencyRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No currency exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_CurrencyRecord();
        }

        $record->name = $model->name;
        $record->iso = strtoupper($model->iso);
        $record->rate = $model->rate;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {
            // Save it!
            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param int $id
     *
     * @throws \CDbException
     */
    public function deleteCurrencyById($id)
    {
        Commerce_CurrencyRecord::model()->deleteByPk($id);
    }
}
