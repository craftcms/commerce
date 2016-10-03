<?php
namespace Craft;

/**
 * Shipping rule service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
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
    public function getAllShippingRules($criteria = [])
    {
        $results = Commerce_ShippingRuleRecord::model()->findAll($criteria);

        return Commerce_ShippingRuleModel::populateModels($results);
    }

    /**
     * @param int $id
     *
     * @return Commerce_ShippingRuleModel[]
     */
    public function getAllShippingRulesByShippingMethodId($id)
    {
        $results = Commerce_ShippingRuleRecord::model()->findAllByAttributes(['methodId' => $id], ['order' => 'priority ASC']);

        return Commerce_ShippingRuleModel::populateModels($results);
    }

    /**
     * @param int $id
     *
     * @return Commerce_ShippingRuleModel|null
     */
    public function getShippingRuleById($id)
    {
        $result = Commerce_ShippingRuleRecord::model()->findById($id);

        if ($result) {
            return Commerce_ShippingRuleModel::populateModel($result);
        }

        return null;
    }

    public function getShippingRuleCategoryByRuleId($id)
    {
        $result = Commerce_ShippingRuleCategoryRecord::model()->findAllByAttributes(['shippingRuleId' => $id]);

        return Commerce_ShippingRuleCategoryModel::populateModels($result,'shippingCategoryId');
    }

    /**
     * @param Commerce_ShippingRuleModel $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveShippingRule(Commerce_ShippingRuleModel $model)
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

        $record->shippingZoneId = $model->shippingZoneId ? $model->shippingZoneId : null;

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

            Commerce_ShippingRuleCategoryRecord::model()->deleteAllByAttributes([
                'shippingRuleId' => $model->id
            ]);

            // Generate a rule category record for all categories regardless of data submitted
            foreach (craft()->commerce_shippingCategories->getAllShippingCategories() as $shippingCategory)
            {
                /** @var Commerce_ShippingRuleCategoryModel $ruleCategory */
                if(isset($model->getShippingRuleCategories()[$shippingCategory->id]) && $ruleCategory = $model->getShippingRuleCategories()[$shippingCategory->id])
                {
                    $data = [
                      'shippingRuleId' => $model->id,
                      'shippingCategoryId' => $shippingCategory->id,
                      'condition' => $ruleCategory->condition,
                      'perItemRate' => is_numeric($ruleCategory->perItemRate) ? $ruleCategory->perItemRate : null,
                      'weightRate' => is_numeric($ruleCategory->weightRate) ? $ruleCategory->weightRate : null,
                      'percentageRate' => is_numeric($ruleCategory->percentageRate) ? $ruleCategory->percentageRate : null
                    ];
                    craft()->db->createCommand()->insert('commerce_shippingrule_categories',$data);
                }else
                {
                    $data = [
                      'shippingRuleId' => $model->id,
                      'shippingCategoryId' => $shippingCategory->id,
                      'condition' => Commerce_ShippingRuleCategoryRecord::CONDITION_ALLOW
                    ];
                    craft()->db->createCommand()->insert('commerce_shippingrule_categories',$data);
                }
            }


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
    public function reorderShippingRules($ids)
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
    public function deleteShippingRuleById($id)
    {
        Commerce_ShippingRuleRecord::model()->deleteByPk($id);
    }
}
