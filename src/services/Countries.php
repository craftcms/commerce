<?php
namespace craft\commerce\services;

use craft\commerce\models\Country;
use craft\commerce\records\Country as CountryRecord;
use craft\commerce\records\Country as CountryRecord;
use craft\helpers\ArrayHelper;
use yii\base\Component;
use yii\base\Exception;

/**
 * Country service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Countries extends Component
{
    /**
     * @param int $id
     *
     * @return Country|null
     */
    public function getCountryById($id)
    {
        $result = CountryRecord::findOne($id);

        if ($result) {
            return new Country($result);
        }

        return null;
    }

    /**
     * @param array $attr
     *
     * @return Country|null
     */
    public function getCountryByAttributes(array $attr)
    {
        $result = CountryRecord::model()->findByAttributes($attr);

        if ($result) {
            return Country::populateModel($result);
        }

        return null;
    }

    /**
     * Simple list for using in forms
     *
     * @return array [id => name]
     */
    public function getAllCountriesListData()
    {
        $countries = $this->getAllCountries();

        return ArrayHelper::map($countries, 'id', 'name');
    }

    /**
     * @return Country[]
     */
    public function getAllCountries()
    {
        $records = CountryRecord::model()->findAll(['order' => 'name']);

        return Country::populateModels($records);
    }

    /**
     * @param Country $model
     *
     * @return bool
     * @throws Exception
     */
    public function saveCountry(Country $model)
    {
        if ($model->id) {
            $record = CountryRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No country exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new CountryRecord();
        }

        $record->name = $model->name;
        $record->iso = strtoupper($model->iso);
        $record->stateRequired = $model->stateRequired;

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
    public function deleteCountryById($id)
    {
        CountryRecord::model()->deleteByPk($id);
    }
}
