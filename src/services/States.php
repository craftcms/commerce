<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\State;
use craft\commerce\Plugin;
use craft\commerce\records\State as StateRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use yii\base\Component;
use yii\base\Exception;

/**
 * State service.
 *
 * @property State[] $allStates an array of all states
 * @property array $allStatesAsList
 * @property array $statesGroupedByCountries all states grouped by countries
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
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
    private $_enabledStatesById = [];

    /**
     * @var State[]
     */
    private $_statesAsOrdered = [];

    /**
     * @var State[]
     */
    private $_enabledStatesAsOrdered = [];

    /**
     * @var State[][]
     */
    private $_statesByTaxZoneId = [];

    /**
     * @var State[][]
     */
    private $_statesByShippingZoneId = [];


    /**
     * Returns a state by its ID.
     *
     * @param int $id the state's ID
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
     * @param int $countryId the state's country ID
     * @param string $abbreviation the state's abbreviation
     * @return State|null
     */
    public function getStateByAbbreviation(int $countryId, string $abbreviation)
    {
        $result = $this->_createStatesQuery()
            ->where(compact('countryId', 'abbreviation'))
            ->one();

        return $result ? new State($result) : null;
    }

    /**
     * Returns all states indexed by ID
     *
     * @return array
     */
    public function getAllStatesAsList(): array
    {
        $states = $this->getAllStates();
        return ArrayHelper::map($states, 'id', 'name');
    }

    /**
     * Returns all states grouped by countries.
     *
     * @return array 2D array of states indexed by their ids grouped by country ids.
     * @since 3.0
     */
    public function getAllStatesAsListGroupedByCountryId(): array
    {
        $states = $this->getAllEnabledStates();
        $cid2state = [];

        foreach ($states as $state) {
            $cid2state += [$state->countryId => []];
            $cid2state[$state->countryId][$state->id] = $state->name;
        }

        return $cid2state;
    }

    /**
     * Returns all enabled states as array indexed by ID
     *
     * @return array
     * @since 3.0
     */
    public function getAllEnabledStatesAsList(): array
    {
        $states = $this->getAllEnabledStates();
        return ArrayHelper::map($states, 'id', 'name');
    }

    /**
     * Returns all enabled states grouped by countries.
     *
     * @return array 2D array of states indexed by their ids grouped by country ids.
     * @since 3.0
     */
    public function getAllEnabledStatesAsListGroupedByCountryId(): array
    {
        $states = $this->getAllEnabledStates();
        $cid2state = [];

        foreach ($states as $state) {
            $cid2state += [$state->countryId => []];
            $cid2state[$state->countryId][$state->id] = $state->name;
        }

        return $cid2state;
    }

    /**
     * Returns an array of all states.
     *
     * @return State[] An array of all states.
     */
    public function getAllStates(): array
    {
        if (!$this->_fetchedAllStates) {
            $results = $this->_createStatesQuery()
                ->innerJoin(Table::COUNTRIES . ' countries', '[[states.countryId]] = [[countries.id]]')
                ->addSelect('[[countries.enabled]] as countryEnabled')
                ->orderBy(['countries.sortOrder' => SORT_ASC, 'states.sortOrder' => SORT_ASC])
                ->all();

            foreach ($results as $row) {
                $countryEnabled = $row['countryEnabled'];
                unset($row['countryEnabled']);

                $state = new State($row);
                $this->_statesById[$row['id']] = $state;
                $this->_statesAsOrdered[] = $state;

                if ($state->enabled && $countryEnabled) {
                    $this->_enabledStatesById[$row['id']] = $state;
                    $this->_enabledStatesAsOrdered[] = $state;
                }
            }

            $this->_fetchedAllStates = true;
        }

        return $this->_statesAsOrdered;
    }

    /**
     * @param int $countryId
     * @return array
     */
    public function getStatesByCountryId(int $countryId): array
    {
        return ArrayHelper::where($this->getAllStates(), 'countryId', $countryId);
    }

    /**
     * Returns an array of all enabled states.
     *
     * @return State[] An array of all enabled states.
     * @since 3.0
     */
    public function getAllEnabledStates(): array
    {
        $this->getAllStates();

        return $this->_enabledStatesAsOrdered;
    }

    /**
     * Returns all states in a tax zone.
     *
     * @param int $taxZoneId the tax zone's ID
     * @return State[] Array of states in the matched tax zone.
     */
    public function getStatesByTaxZoneId(int $taxZoneId): array
    {
        if (!isset($this->_statesByTaxZoneId[$taxZoneId])) {
            $results = $this->_createStatesQuery()
                ->innerJoin(Table::TAXZONE_STATES . ' taxZoneStates', '[[states.id]] = [[taxZoneStates.stateId]]')
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
     * @return State[] Array of states in the matched shipping zone.
     */
    public function getStatesByShippingZoneId($shippingZoneId): array
    {
        if (!isset($this->_statesByShippingZoneId[$shippingZoneId])) {
            $results = $this->_createStatesQuery()
                ->innerJoin(Table::SHIPPINGZONE_STATES . ' shippingZoneStates', '[[states.id]] = [[shippingZoneStates.stateId]]')
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
     * Saves a state.
     *
     * @param State $model The state to be saved.
     * @param bool $runValidation should we validate this state before saving.
     * @return bool Whether the state was saved successfully.
     * @throws Exception if the sate does not exist.
     */
    public function saveState(State $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = StateRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Plugin::t('No state exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new StateRecord();
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('State not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->name = $model->name;
        $record->abbreviation = $model->abbreviation;
        $record->countryId = $model->countryId;
        $record->enabled = (bool)$model->enabled;

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;

        return true;
    }

    /**
     * Deletes a state by its ID.
     *
     * @param int $id the state's ID
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

    /**
     * @param array $ids
     * @return bool
     * @throws \yii\db\Exception
     * @since 3.1
     */
    public function reorderStates(array $ids): bool
    {
        $command = Craft::$app->getDb()->createCommand();

        foreach ($ids as $index => $id) {
            $command->update(Table::STATES, ['sortOrder' => $index + 1], ['id' => $id])->execute();
        }

        return true;
    }

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
                'states.countryId',
                'states.enabled',
                'states.sortOrder'
            ])
            ->from([Table::STATES . ' states'])
            ->orderBy(['states.sortOrder' => SORT_ASC]);
    }
}
