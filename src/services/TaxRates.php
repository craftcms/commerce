<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\TaxAddressZone;
use craft\commerce\models\TaxRate;
use craft\commerce\Plugin;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

/**
 * Tax Rate service.
 *
 * @property TaxRate $liteTaxRate the lite tax rate
 * @property TaxRate[] $allTaxRates an array of all the existing tax rates
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxRates extends Component
{
    /**
     * @var TaxRate[]|null
     */
    private ?array $_allTaxRates = null;

    /**
     * Returns an array of all existing tax rates.
     *
     * @return TaxRate[]
     */
    public function getAllTaxRates(): array
    {
        if (!isset($this->_allTaxRates)) {
            $rows = $this->_createTaxRatesQuery()->all();

            foreach ($rows as $row) {
                $this->_allTaxRates[$row['id']] = new TaxRate($row);
            }
        }

        return $this->_allTaxRates ?? [];
    }

    /**
     * Returns an array of all rates belonging to the specified zone.
     *
     * @param TaxAddressZone $zone The tax zone whose rates we’d like returned
     * @return TaxRate[]
     * @deprecated in 4.0. Use [[getTaxRatesByTaxZoneId]] instead.
     */
    public function getTaxRatesForZone(TaxAddressZone $zone): array
    {
        return $this->getTaxRatesByTaxZoneId($zone->id);
    }

    /**
     * Returns an array of all rates belonging to the specified zone.
     *
     * @param int $taxZoneId The ID of the tax zone whose rates we’d like returned
     * @return TaxRate[]
     */
    public function getTaxRatesByTaxZoneId(int $taxZoneId): array
    {
        return ArrayHelper::where($this->getAllTaxRates(), 'taxZoneId', $taxZoneId);
    }

    /**
     * Returns a tax rate by ID.
     *
     * @param int $id The ID of the desired tax rate
     * @return ?TaxRate
     */
    public function getTaxRateById(int $id): ?TaxRate
    {
        return ArrayHelper::firstWhere($this->getAllTaxRates(), 'id', $id);
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

        // if not an included tax, then can not be removed.
        $record->include = $model->include;
        $record->isVat = $model->isVat;
        $record->removeIncluded = !$record->include ? false : $model->removeIncluded;
        $record->removeVatIncluded = (!$record->include || !$record->isVat) ? false : $model->removeVatIncluded;
        $record->taxable = $model->taxable;
        $record->taxCategoryId = $model->taxCategoryId;
        $record->taxZoneId = $model->taxZoneId ?: null;
        $record->isEverywhere = $model->getIsEverywhere();

        if (!$record->isEverywhere && $record->taxZoneId && empty($record->getErrors('taxZoneId'))) {
            $taxZone = Plugin::getInstance()->getTaxZones()->getTaxZoneById($record->taxZoneId);

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

        return true;
    }

    /**
     * Saves a Commerce Lite tax rate.
     *
     * @param TaxRate $model          The tax rate model to be saved
     * @param bool    $runValidation  Whether we should validate this rate before saving
     * @return bool
     * @throws Exception
     * @throws \Exception
     * @deprecated in 4.5.0. Use [[saveTaxRate()]] instead.
     */
    public function saveLiteTaxRate(TaxRate $model, bool $runValidation = true): bool
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'TaxRates::saveLiteTaxRate() is deprecated. Use TaxRates::saveTaxRate() instead.');
        return $this->saveTaxRate($model, $runValidation);
    }

    /**
     * Returns the Commerce Lite tax rate.
     *
     * @return TaxRate
     * @throws InvalidConfigException
     * @deprecated in 4.5.0. Use [[getAllTaxRates()]] instead.
     */
    public function getLiteTaxRate(): TaxRate
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'TaxRates::getLiteTaxRate() is deprecated. Use TaxRates::getAllTaxRates() instead.');
        $liteRate = $this->_createTaxRatesQuery()->one();

        if ($liteRate == null) {
            $liteRate = new TaxRate();
            $liteRate->name = 'Tax';
            $liteRate->include = false;
            $liteRate->removeIncluded = true;
            $liteRate->removeVatIncluded = true;
            $liteRate->taxCategoryId = Plugin::getInstance()->getTaxCategories()->getDefaultTaxCategory()->id;
            $liteRate->taxable = TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE;
        } else {
            $liteRate = new TaxRate($liteRate);
        }

        return $liteRate;
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
                'taxable',
                'taxCategoryId',
                'taxZoneId',
            ])
            ->orderBy(['include' => SORT_DESC, 'isVat' => SORT_DESC])
            ->from([Table::TAXRATES]);

        return $query;
    }
}
