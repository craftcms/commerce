<?php
namespace Craft;

/**
 * Class Market_TaxRateService
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Market_TaxRateService extends BaseApplicationComponent
{
    /**
     * @param \CDbCriteria|array $criteria
     *
     * @return Market_TaxRateModel[]
     */
    public function getAll($criteria = [])
    {
        $records = Market_TaxRateRecord::model()->findAll($criteria);

        return Market_TaxRateModel::populateModels($records);
    }

    /**
     * @param int $id
     *
     * @return Market_TaxRateModel
     */
    public function getById($id)
    {
        $record = Market_TaxRateRecord::model()->findById($id);

        return Market_TaxRateModel::populateModel($record);
    }

    /**
     * @param Market_TaxRateModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function save(Market_TaxRateModel $model)
    {
        if ($model->id) {
            $record = Market_TaxRateRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No tax rate exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Market_TaxRateRecord();
        }

        $record->name          = $model->name;
        $record->rate          = $model->rate;
        $record->include       = $model->include;
        $record->showInLabel   = $model->showInLabel;
        $record->taxCategoryId = $model->taxCategoryId;
        $record->taxZoneId     = $model->taxZoneId;

        $record->validate();

        if (!$record->getError('taxZoneId')) {
            $taxZone = craft()->market_taxZone->getById($record->taxZoneId);
            if ($record->include && !$taxZone->default) {
                $record->addError('taxZoneId',
                    Craft::t('Included tax rates are only allowed for the default tax zone. Zone selected is not default.'));
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
        } else {
            return false;
        }
    }

    /**
     * @param int $id
     *
     * @throws \CDbException
     */
    public function deleteById($id)
    {
        $TaxRate = Market_TaxRateRecord::model()->findById($id);
        $TaxRate->delete();
    }
}