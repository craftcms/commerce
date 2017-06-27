<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\State;
use craft\commerce\records\State as StateRecord;
use craft\db\Query;
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
     * @var State[]
     */
    private $_statesById = [];

    /**
     * @var State[]
     */
    private $_statesByTaxZoneId;

    /**
     * @param int $id
     *
     * @return State|null
     */
    public function getStateById($id)
    {
        if (!isset($this->_statesById[$id])) {
            $row = $this->_createStatesQuery()
                ->where(['id' => $id])
                ->one();

            $this->_statesById[$id] = $row ? new State($row) : null;
        }

        return $this->_statesById[$id];
    }

    /**
     * @return array [countryId => [stateId => stateName]]
     */
    public function getStatesGroupedByCountries()
    {
        $states = $this->getAllStates();
        $cid2state = [];

        foreach ($states as $state) {

            $cid2state += [$state->countryId => []];

            if (count($cid2state[$state->countryId]) == 0) {
                $cid2state[$state->countryId][null] = '';
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
        $states = $this->_createStatesQuery()
            ->innerJoin('{{%commerce_countries}} countries', '[[states.countryId]] = [[countries.id]]')
            ->orderBy(['countries.name' => SORT_ASC, 'states.name' => SORT_ASC])
            ->all();

        return State::populateModels($states);
    }

    /**
     * Returns all states in a tax zone
     *
     * @param $taxZoneId
     *
     * @return array
     */
    public function getStatesByTaxZoneId($taxZoneId)
    {
        if (null === $this->_statesByTaxZoneId) {
            $this->_statesByTaxZoneId = [];

            $results = $this->_createStatesQuery()
                ->innerJoin('{{%commerce_taxzone_states}} taxZoneStates', '[[states.id]] = [[taxZoneStates.stateId]]')
                ->all();

            $states = [];
            foreach ($results as $result) {
                $states[] = new State($result);
            }

            $this->_statesByTaxZoneId[$taxZoneId] = $states;
        }

        return $this->_statesByTaxZoneId[$taxZoneId] ?? [];
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
                throw new Exception(Craft::t('commerce', 'No state exists with the ID “{id}”',
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
        }

        return false;
    }

    /**
     * @param int $id
     *
     * @throws \CDbException
     */
    public function deleteStateById($id)
    {
        // Nuke the asset volume.
        Craft::$app->getDb()->createCommand()
            ->delete('{{%commerce_states}}', ['id' => $id])
            ->execute();
    }

    // Private methods
    // =========================================================================
    /**
     * Returns a Query object prepped for retrieving States.
     *
     * @return Query
     */
    private function _createStatesQuery(): Query
    {
        return (new Query())
            ->select([
                'states.id',
                'states.name',
                'states.abbreviation',
                'states.countryId'
            ])
            ->from(['{{%commerce_states}} states'])
            ->orderBy(['name' => SORT_ASC]);
    }
}
