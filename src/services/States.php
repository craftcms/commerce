<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\State;
use craft\commerce\records\State as StateRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

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
     * @var bool
     */
    private $_fetchedAllStates = false;

    /**
     * @var State[]
     */
    private $_statesById = [];

    /**
     * @var State[]
     */
    private $_statesOrderedByName = [];

    /**
     * @var State[][]
     */
    private $_statesByTaxZoneId = [];

    /**
     * @var State[][]
     */
    private $_statesByShippingZoneId = [];

    /**
     * @param int $id
     *
     * @return State|null
     */
    public function getStateById($id)
    {
        if (isset($this->_statesById[$id])) {
            return $this->_statesById[$id];
        }

        if ($this->_fetchedAllStates) {
            return null;
        }

        $row = $this->_createStatesQuery()
            ->where(['id' => $id])
            ->one();

        if (!$row) {
            return null;
        }

        return $this->_statesById[$id] = new State($row);
    }

    /**
     * @return array [countryId => [stateId => stateName]]
     */
    public function getStatesGroupedByCountries(): array
    {
        $states = $this->getAllStates();
        $cid2state = [];

        foreach ($states as $state) {
            $cid2state += [$state->countryId => []];

            if (!count($cid2state[$state->countryId])) {
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
        if (!$this->_fetchedAllStates) {
            $results = $this->_createStatesQuery()
                ->innerJoin('{{%commerce_countries}} countries', '[[states.countryId]] = [[countries.id]]')
                ->orderBy(['countries.name' => SORT_ASC, 'states.name' => SORT_ASC])
                ->all();

            foreach ($results as $row ) {
                $state = new State($row);
                $this->_statesById[$row['id']] = $state;
                $this->_statesOrderedByName[] = $state;
            }

            $this->_fetchedAllStates;
        }

        return $this->_statesOrderedByName;
    }

    /**
     * Returns all states in a tax zone
     *
     * @param $taxZoneId
     *
     * @return States[]
     */
    public function getStatesByTaxZoneId($taxZoneId): array
    {
        if (!isset($this->_statesByTaxZoneId[$taxZoneId])) {
            $results = $this->_createStatesQuery()
                ->innerJoin('{{%commerce_taxzone_states}} taxZoneStates', '[[states.id]] = [[taxZoneStates.stateId]]')
                ->where(['taxZoneStates.taxZoneId' => $taxZoneId])
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
     * Returns all states in a shipping zone
     *
     * @param $shippingZoneId
     *
     * @return States[]
     */
    public function getStatesByShippingZoneId($shippingZoneId): array
    {
        if (!isset($this->_statesByShippingZoneId[$shippingZoneId])) {
            $results = $this->_createStatesQuery()
                ->innerJoin('{{%commerce_shippingzone_states}} shippingZoneStates', '[[states.id]] = [[shippingZoneStates.stateId]]')
                ->where(['shippingZoneStates.shippingZoneId' => $shippingZoneId])
                ->all();

            $states = [];

            foreach ($results as $result) {
                $states[] = new State($result);
            }

            $this->_statesByShippingZoneId[$shippingZoneId] = $states;
        }

        return $this->_statesByShippingZoneId[$shippingZoneId] ?? [];
    }

    /**
     * @param State $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveState(State $model): bool
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
     * @return bool
     */
    public function deleteStateById($id): bool
    {
        $record = StateRecord::findOne($id);

        if ($record) {
            return (bool)$record->delete();
        }

        return false;
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
