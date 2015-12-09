<?php
namespace Craft;

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
class Commerce_CountriesService extends BaseApplicationComponent
{
    /**
     * @param int $id
     *
     * @return Commerce_CountryModel|null
     */
    public function getCountryById($id)
    {
        $result = Commerce_CountryRecord::model()->findById($id);

        if ($result) {
            return Commerce_CountryModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param array $attr
     *
     * @return Commerce_CountryModel|null
     */
    public function getCountryByAttributes(array $attr)
    {
        $result = Commerce_CountryRecord::model()->findByAttributes($attr);

        if ($result) {
            return Commerce_CountryModel::populateModel($result);
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

        return \CHtml::listData($countries, 'id', 'name');
    }

    /**
     * @return Commerce_CountryModel[]
     */
    public function getAllCountries()
    {
        $records = Commerce_CountryRecord::model()->findAll(['order' => 'name']);

        return Commerce_CountryModel::populateModels($records);
    }

    /**
     * @param Commerce_CountryModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveCountry(Commerce_CountryModel $model)
    {
        if ($model->id) {
            $record = Commerce_CountryRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No country exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_CountryRecord();
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
        Commerce_CountryRecord::model()->deleteByPk($id);
    }
}
