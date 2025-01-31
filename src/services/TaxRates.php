<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\errors\StoreNotFoundException;
use craft\commerce\models\TaxRate;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\db\Query;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

/**
 * Tax Rate service.
 *
 * @property TaxRate[] $allTaxRates an array of all the existing tax rates
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxRates extends Component
{
    /**
     * @var Collection<TaxRate>[]|null
     */
    private ?array $_allTaxRates = null;

    /**
     * Returns an array of all existing tax rates.
     *
     * @param int|null $storeId
     * @return Collection
     * @throws StoreNotFoundException
     * @throws InvalidConfigException
     */
    public function getAllTaxRates(?int $storeId = null): Collection
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        if ($this->_allTaxRates === null || !isset($this->_allTaxRates[$storeId])) {
            $results = $this->_createTaxRatesQuery()
                ->where(['storeId' => $storeId])
                ->all();

            if ($this->_allTaxRates === null) {
                $this->_allTaxRates = [];
            }

            foreach ($results as $result) {
                $taxRate = Craft::createObject([
                    'class' => TaxRate::class,
                    'attributes' => $result,
                ]);

                if (!isset($this->_allTaxRates[$taxRate->storeId])) {
                    $this->_allTaxRates[$taxRate->storeId] = collect();
                }

                $this->_allTaxRates[$taxRate->storeId]->push($taxRate);
            }
        }

        return $this->_allTaxRates[$storeId] ?? collect();
    }

    /**
     * @param int|null $storeId
     * @return Collection
     * @throws InvalidConfigException
     * @throws StoreNotFoundException
     * @since 5.3.0
     */
    public function getAllEnabledTaxRates(?int $storeId = null): Collection
    {
        return $this->getAllTaxRates($storeId)->where('enabled', true);
    }

    /**
     * Returns an array of all rates belonging to the specified zone.
     *
     * @param int $taxZoneId The ID of the tax zone whose rates we’d like returned
     * @param int|null $storeId
     * @return Collection
     * @throws InvalidConfigException
     * @throws StoreNotFoundException
     */
    public function getTaxRatesByTaxZoneId(int $taxZoneId, ?int $storeId = null): Collection
    {
        return $this->getAllTaxRates($storeId)->where('taxZoneId', $taxZoneId);
    }

    /**
     * Returns a tax rate by ID.
     *
     * @param int $id The ID of the desired tax rate
     * @param int|null $storeId
     * @return ?TaxRate
     * @throws InvalidConfigException
     * @throws StoreNotFoundException
     */
    public function getTaxRateById(int $id, ?int $storeId = null): ?TaxRate
    {
        return $this->getAllTaxRates($storeId)->firstWhere('id', $id);
    }

    /**
     * Saves a tax rate.
     *
     * @param TaxRate $model          The tax rate model to be saved
     * @param bool    $runValidation  Whether we should validate this rate before saving
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function saveTaxRate(TaxRate $model, bool $runValidation = true): bool
    {
        if ($model->id) {
            $record = TaxRateRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No tax rate exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new TaxRateRecord();
        }

        if ($runValidation && !$model->validate()) {
            Craft::info('Tax rate not saved due to validation error.', __METHOD__);

            return false;
        }

        $record->name = $model->name;
        $record->code = $model->code;
        $record->rate = $model->rate;
        $record->storeId = $model->storeId;

        // if not an included tax, then can not be removed.
        $record->include = $model->include;
        $record->isVat = $model->hasTaxIdValidators();
        $record->removeIncluded = !$record->include ? false : $model->removeIncluded;
        $record->removeVatIncluded = (!$record->include || !$record->isVat) ? false : $model->removeVatIncluded;
        $record->taxable = $model->taxable;
        $record->taxCategoryId = $model->taxCategoryId;
        $record->taxZoneId = $model->taxZoneId ?: null;
        $record->isEverywhere = $model->getIsEverywhere();
        $record->enabled = $model->enabled;
        $record->taxIdValidators = $model->taxIdValidators;

        if (!$record->isEverywhere && $record->taxZoneId && empty($record->getErrors('taxZoneId'))) {
            $taxZone = Plugin::getInstance()->getTaxZones()->getTaxZoneById($record->taxZoneId, $record->storeId);

            if (!$taxZone) {
                throw new Exception(Craft::t('commerce', 'No tax zone exists with the ID “{id}”', ['id' => $record->taxZoneId]));
            }

            if ($record->removeIncluded && !$taxZone->default) {
                $model->addError('removeIncluded', Craft::t('commerce', 'Removable included tax rates are only allowed for the default tax zone.'));

                return false;
            }
        }

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;
        $this->clearCache();

        return true;
    }

    /**
     * Deletes a tax rate by ID.
     *
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function deleteTaxRateById(int $id): bool
    {
        $record = TaxRateRecord::findOne($id);

        if ($record) {
            $this->clearCache();
            return (bool)$record->delete();
        }

        return false;
    }

    /**
     * Returns a Query object prepped for retrieving tax rates
     */
    private function _createTaxRatesQuery(): Query
    {
        $query = (new Query())
            ->select([
                'code',
                'dateCreated',
                'dateUpdated',
                'id',
                'include',
                'isVat',
                'name',
                'rate',
                'removeIncluded',
                'removeVatIncluded',
                'storeId',
                'taxable',
                'taxCategoryId',
                'taxZoneId',
            ])
            ->orderBy(['include' => SORT_DESC, 'isVat' => SORT_DESC])
            ->from([Table::TAXRATES]);

        // if enabled column exists add the select
        if (Craft::$app->getDb()->columnExists(Table::TAXRATES, 'enabled')) {
            $query->addSelect(['enabled']);
        }

        // add taxIdValidators select
        if (Craft::$app->getDb()->columnExists(Table::TAXRATES, 'taxIdValidators')) {
            $query->addSelect(['taxIdValidators']);
        }

        return $query;
    }

    /**
     * @return void
     * @since 5.0.0
     */
    protected function clearCache(): void
    {
        $this->_allTaxRates = null;
    }
}
