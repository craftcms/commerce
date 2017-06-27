<?php

namespace craft\commerce\services;

use craft\commerce\models\TaxRate;
use craft\commerce\records\TaxRate as TaxRateRecord;
use yii\base\Component;

/**
 * Tax rate service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class TaxRates extends Component
{
    /**
     *
     * @return TaxRate[]
     */
    public function getAllTaxRates()
    {
        $records = TaxRateRecord::find()->all();

        return TaxRate::populateModels($records);
    }

    /**
     *
     * @return TaxRate[]
     */
    public function getAllTaxRatesWithCountries(): array
    {
        $records = TaxRateRecord::find()->with([
            'taxZone',
            'taxZone.countries',
            'taxZone.states.country'
        ])->all();

        return TaxRate::populateModels($records);
    }

    /**
     *
     * @return TaxRate[]
     */
    public function getAllTaxRatesWithZoneAndCategories(): array
    {
        $records = TaxRateRecord::find()->with(['taxZone', 'taxCategory'])->all();

        return TaxRate::populateModels($records);
    }


    /**
     * @param int $id
     *
     * @return TaxRate|null
     */
    public function getTaxRateById($id)
    {
        $result = TaxRateRecord::findOne($id);

        if ($result) {
            return new TaxRate($result);
        }

        return null;
    }

    /**
     * @param TaxRate $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveTaxRate(TaxRate $model)
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

        $record->name = $model->name;
        $record->rate = $model->rate;
        $record->include = $model->include;
        $record->isVat = $model->isVat;
        $record->taxable = $model->taxable;
        $record->taxCategoryId = $model->taxCategoryId;
        $record->taxZoneId = $model->taxZoneId;

        $record->validate();

        if ($record->taxZoneId && !$record->getError('taxZoneId')) {
            $taxZone = Plugin::getInstance()->getTaxZones()->getTaxZoneById($record->taxZoneId);

            if (!$taxZone) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No tax zone exists with the ID “{id}”', ['id' => $record->taxZoneId]));
            }

            if ($record->include && !$taxZone->default) {
                $record->addError('taxZoneId',
                    Craft::t('commerce', 'commerce', 'Included tax rates are only allowed for the default tax zone. Zone selected is not default.'));
            }
        }

        $model->validate();
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
    public function deleteTaxRateById($id)
    {
        $TaxRate = TaxRateRecord::findOne($id);
        $TaxRate->delete();
    }
}
