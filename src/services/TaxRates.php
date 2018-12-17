<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\TaxAddressZone;
use craft\commerce\models\TaxRate;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\db\Query;
use yii\base\Component;
use yii\base\Exception;

/**
 * Tax rate service.
 *
 * @property array|TaxRate[] $allTaxRates an array of all of the existing tax rates
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxRates extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var bool
     */
    private $_fetchedAllTaxRates = false;

    /**
     * @var TaxRate[]
     */
    private $_allTaxRates = [];

    // Public Methods
    // =========================================================================

    /**
     * Returns an array of all of the existing tax rates.
     *
     * @return TaxRate[]
     */
    public function getAllTaxRates(): array
    {
        if (!$this->_fetchedAllTaxRates) {
            $rows = $this->_createTaxRatesQuery()->all();

            foreach ($rows as $row) {
                $this->_allTaxRates[$row['id']] = new TaxRate($row);
            }

            $this->_fetchedAllTaxRates = true;
        }

        return $this->_allTaxRates;
    }

    /**
     * Returns an array of all of the rates belonging to the zone
     *
     * @param TaxAddressZone $zone
     *
     * @return TaxRate[]
     */
    public function getTaxRatesForZone(TaxAddressZone $zone): array
    {
        $allTaxRates = $this->getAllTaxRates();
        $taxRates = [];

        /** @var \craft\commerce\models\TaxRate $rate */
        foreach ($allTaxRates as $rate) {
            if ($zone->id === $rate->taxZoneId) {
                $taxRates[] = $rate;
            }
        }

        return $taxRates;
    }

    /**
     * Returns a tax rate by ID.
     *
     * @param int $id
     * @return TaxRate|null
     */
    public function getTaxRateById($id)
    {
        if (isset($this->_allTaxRates[$id])) {
            return $this->_allTaxRates[$id];
        }

        if ($this->_fetchedAllTaxRates) {
            return null;
        }

        $result = $this->_createTaxRatesQuery()
            ->where(['id' => $id])
            ->one();

        if (!$result) {
            return null;
        }

        return $this->_allTaxRates[$id] = new TaxRate($result);
    }

    /**
     * @param TaxRate $model
     * @param bool $runValidation should we validate this rate before saving.
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
        $record->rate = $model->rate;
        $record->include = $model->include;
        $record->isVat = $model->isVat;
        $record->taxable = $model->taxable;
        $record->taxCategoryId = $model->taxCategoryId;
        $record->taxZoneId = $model->taxZoneId;

        if ($record->taxZoneId && empty($record->getErrors('taxZoneId'))) {
            $taxZone = Plugin::getInstance()->getTaxZones()->getTaxZoneById($record->taxZoneId);

            if (!$taxZone) {
                throw new Exception(Craft::t('commerce', 'No tax zone exists with the ID “{id}”', ['id' => $record->taxZoneId]));
            }

            if ($record->include && !$taxZone->default) {
                $model->addError('include', Craft::t('commerce', 'Included tax rates are only allowed for the default tax zone.'));

                return false;
            }
        }

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;

        return true;
    }

    /**
     * Deletes a tax rate by ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteTaxRateById($id): bool
    {
        $record = TaxRateRecord::findOne($id);

        if ($record) {
            return (bool)$record->delete();
        }

        return false;
    }

    // Private methods
    // =========================================================================
    /**
     * Returns a Query object prepped for retrieving tax rates
     *
     * @return Query
     */
    private function _createTaxRatesQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'taxZoneId',
                'taxCategoryId',
                'name',
                'rate',
                'include',
                'isVat',
                'taxable',
            ])
            ->from(['{{%commerce_taxrates}}']);
    }
}
