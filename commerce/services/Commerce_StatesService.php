<?php
namespace Craft;

/**
 * State service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_StatesService extends BaseApplicationComponent
{
    /**
     * @param int $id
     *
     * @return Commerce_StateModel|null
     */
    public function getStateById($id)
    {
        $result = Commerce_StateRecord::model()->findById($id);

        if ($result) {
            return Commerce_StateModel::populateModel($result);
        }

        return null;
    }

    /**
     * @param array $attr
     *
     * @return Commerce_StateModel|null
     */
    public function getStateByAttributes(array $attr)
    {
        $result = Commerce_StateRecord::model()->findByAttributes($attr);

        if ($result) {
            return Commerce_StateModel::populateModel($result);
        }

        return null;
    }

    /**
     * @return array [countryId => [stateId => stateName]]
     */
    public function getStatesGroupedByCountries()
    {
        $states = craft()->commerce_states->getAllStates();
        $cid2state = [];

        foreach ($states as $state) {

            $cid2state += [$state->countryId => []];

            if (count($cid2state[$state->countryId]) == 0) {
                $cid2state[$state->countryId][null] = "";
            }

            $cid2state[$state->countryId][$state->id] = $state->name;

        }

        return $cid2state;
    }

    /**
     * @return Commerce_StateModel[]
     */
    public function getAllStates()
    {
        $records = Commerce_StateRecord::model()->with('country')->findAll(['order' => 'country.name, t.name']);

        return Commerce_StateModel::populateModels($records);
    }

    /**
     * @param Commerce_StateModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveState(Commerce_StateModel $model)
    {
        if ($model->id) {
            $record = Commerce_StateRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No state exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_StateRecord();
        }

        $record->name = $model->name;
        $record->abbreviation = $model->abbreviation;
        $record->countryId = $model->countryId;

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
    public function deleteStateById($id)
    {
        $State = Commerce_StateRecord::model()->findById($id);
        $State->delete();
    }
}
