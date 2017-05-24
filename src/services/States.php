<?php

namespace craft\commerce\services;

use craft\commerce\models\State;
use craft\commerce\Plugin;
use craft\commerce\records\State as StateRecord;
use yii\base\Component;

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
class States extends Component
{
    /**
     * @param int $id
     *
     * @return State|null
     */
    public function getStateById($id)
    {
        $result = StateRecord::findOne($id);

        if ($result) {
            return State::populateModel($result);
        }

        return null;
    }

    /**
     * @param array $attr
     *
     * @return State|null
     */
    public function getStateByAttributes(array $attr)
    {
        $result = StateRecord::find()->where($attr)->all();

        if ($result) {
            return new State($result);
        }

        return null;
    }

    /**
     * @return array [countryId => [stateId => stateName]]
     */
    public function getStatesGroupedByCountries()
    {
        $states = Plugin::getInstance()->getStates()->getAllStates();
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
     * @return State[]
     */
    public function getAllStates(): array
    {
        $records = StateRecord::find()->with('country c')->alias('s')->orderBy('c.name, s.name')->all();

        return State::populateModels($records);
    }

    /**
     * @param State $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveState(State $model)
    {
        if ($model->id) {
            $record = StateRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No state exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new StateRecord();
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
        $State = StateRecord::findOne($id);
        $State->delete();
    }
}
