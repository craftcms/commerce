<?php
namespace Craft;

/**
 * Shipping rule service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Commerce_ShippingRulesService extends BaseApplicationComponent
{
    /**
     * @param array|\CDbCriteria $criteria
     *
     * @return Commerce_ShippingRuleModel[]
     */
    public function getAll($criteria = [])
    {
        $records = Commerce_ShippingRuleRecord::model()->findAll($criteria);

        return Commerce_ShippingRuleModel::populateModels($records);
    }

    /**
     * @param int $id
     *
     * @return Commerce_ShippingRuleModel[]
     */
    public function getAllByMethodId($id)
    {
        $records = Commerce_ShippingRuleRecord::model()->findAllByAttributes(['methodId' => $id], ['order' => 'priority ASC']);

        return Commerce_ShippingRuleModel::populateModels($records);
    }

    /**
     * @param int $id
     *
     * @return Commerce_ShippingRuleModel
     */
    public function getById($id)
    {
        $record = Commerce_ShippingRuleRecord::model()->findById($id);

        return Commerce_ShippingRuleModel::populateModel($record);
    }

    /**
     * @param Commerce_ShippingRuleModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function save(Commerce_ShippingRuleModel $model)
    {
        if ($model->id) {
            $record = Commerce_ShippingRuleRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('No shipping rule exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new Commerce_ShippingRuleRecord();
        }

        $fields = [
            'name',
            'description',
            'countryId',
            'stateId',
            'methodId',
            'enabled',
            'minQty',
            'maxQty',
            'minTotal',
            'maxTotal',
            'minWeight',
            'maxWeight',
            'baseRate',
            'perItemRate',
            'weightRate',
            'percentageRate',
            'minRate',
            'maxRate'
        ];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        if (empty($record->priority) && empty($model->priority)) {
            $count = Commerce_ShippingRuleRecord::model()->countByAttributes(['methodId' => $model->methodId]);
            $record->priority = $model->priority = $count + 1;
        } elseif ($model->priority) {
            $record->priority = $model->priority;
        } else {
            $model->priority = $record->priority;
        }

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
     * @param $ids
     *
     * @return bool
     */
    public function reorder($ids)
    {
        foreach ($ids as $sortOrder => $id) {
            craft()->db->createCommand()->update('commerce_shippingrules',
                ['priority' => $sortOrder + 1], ['id' => $id]);
        }

        return true;
    }

    /**
     * @param int $id
     */
    public function deleteById($id)
    {
        Commerce_ShippingRuleRecord::model()->deleteByPk($id);
    }
}
