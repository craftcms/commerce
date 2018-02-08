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
 * @property State[]|array $allStates
 * @property array         $statesGroupedByCountries
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class States extends Component
{
    // Properties
    // =========================================================================

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

    // Public Methods
    // =========================================================================

    /**
     * Returns a state by its ID.
     *
     * @param int $id the state's ID
     *
     * @return State|null
     */
    public function getStateById(int $id)
    {
        if (isset($this->_statesById[$id])) {
            return $this->_statesById[$id];
        }

        if ($this->_fetchedAllStates) {
            return null;
        }

        $result = $this->_createStatesQuery()
            ->where(['id' => $id])
            ->one();

        if (!$result) {
            return null;
        }

        return $this->_statesById[$id] = new State($result);
    }

    /**
     * Returns a state by its abbreviation.
     *
     * @param int    $countryId    the state's country ID
     * @param string $abbreviation the state's abbreviation
     *
     * @return State|null
     */
    public function getStateByAbbreviation(int $countryId, string $abbreviation)
    {
        $result = $this->_createStatesQuery()
            ->where(['countryId' => $countryId, 'abbreviation' => $abbreviation])
            ->one();

        return $result ? new State($result) : null;
    }

    /**
     * Get all states grouped by countries.
     *
     * @return array 2D array of states indexed by their ids grouped by country ids.
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
     * Get an array of all states.
     *
     * @return State[] An array of all states.
     */
    public function getAllStates(): array
    {
        if (!$this->_fetchedAllStates) {
            $results = $this->_createStatesQuery()
                ->innerJoin('{{%commerce_countries}} countries', '[[states.countryId]] = [[countries.id]]')
                ->orderBy(['countries.name' => SORT_ASC, 'states.name' => SORT_ASC])
                ->all();

            foreach ($results as $row) {
                $state = new State($row);
                $this->_statesById[$row['id']] = $state;
                $this->_statesOrderedByName[] = $state;
            }

            $this->_fetchedAllStates;
        }

        return $this->_statesOrderedByName;
    }

    /**
     * Returns all states in a tax zone.
     *
     * @param int $taxZoneId the tax zone's ID
     *
     * @return State[] Array of states in the matched tax zone.
     */
    public function getStatesByTaxZoneId(int $taxZoneId): array
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

        return $this->_statesByTaxZoneId[$taxZoneId];
    }

    /**
     * Returns all states in a shipping zone.
     *
     * @param int $shippingZoneId the shipping zone's ID
     *
     * @return State[] Array of states in the matched shipping zone.
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

        return $this->_statesByShippingZoneId[$shippingZoneId];
    }

    /**
     * Save a state.
     *
     * @param State $model The state to be saved.
     *
     * @return bool Whether the state was saved successfully.
     * @throws Exception if the sate does not exist.
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
     * Deletes a state by its ID.
     *
     * @param int $id the state's ID
     *
     * @return bool whether the state was deleted successfully
     */
    public function deleteStateById(int $id): bool
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
     * @return Query The query object.
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
