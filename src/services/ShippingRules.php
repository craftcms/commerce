<?php
namespace craft\commerce\services;

use craft\commerce\base\Model\ShippingRuleCategory;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\ShippingRule;
use craft\commerce\records\ShippingRule as ShippingRuleRecord;
use craft\commerce\records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use yii\base\Component;

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
class ShippingRules extends Component
{
    /**
     * @param array|\CDbCriteria $criteria
     *
     * @return ShippingRule[]
     */
    public function getAllShippingRules($criteria = [])
    {
        $results = ShippingRuleRecord::model()->findAll($criteria);

        return ShippingRule::populateModels($results);
    }

    /**
     * @param int $id
     *
     * @return ShippingRule[]
     */
    public function getAllShippingRulesByShippingMethodId($id)
    {
        $results = ShippingRuleRecord::model()->findAllByAttributes(['methodId' => $id], ['order' => 'priority ASC']);

        return ShippingRule::populateModels($results);
    }

    /**
     * @param int $id
     *
     * @return ShippingRule|null
     */
    public function getShippingRuleById($id)
    {
        $result = ShippingRuleRecord::model()->findById($id);

        if ($result) {
            return ShippingRule::populateModel($result);
        }

        return null;
    }

    public function getShippingRuleCategoryByRuleId($id)
    {
        $result = ShippingRuleCategoryRecord::model()->findAllByAttributes(['shippingRuleId' => $id]);

        return ShippingRuleCategory::populateModels($result, 'shippingCategoryId');
    }

    /**
     * @param ShippingRule $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function saveShippingRule(ShippingRule $model)
    {
        if ($model->id) {
            $record = ShippingRuleRecord::model()->findById($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No shipping rule exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new ShippingRuleRecord();
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
            $count = ShippingRuleRecord::model()->countByAttributes(['methodId' => $model->methodId]);
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

            ShippingRuleCategoryRecord::model()->deleteAllByAttributes([
                'shippingRuleId' => $model->id
            ]);

            // Generate a rule category record for all categories regardless of data submitted
            foreach (Plugin::getInstance()->getShippingCategories()->getAllShippingCategories() as $shippingCategory) {
                /** @var ShippingCategory $ruleCategory */
                if (isset($model->getShippingRuleCategories()[$shippingCategory->id]) && $ruleCategory = $model->getShippingRuleCategories()[$shippingCategory->id]) {
                    $data = [
                        'shippingRuleId' => $model->id,
                        'shippingCategoryId' => $shippingCategory->id,
                        'condition' => $ruleCategory->condition,
                        'perItemRate' => is_numeric($ruleCategory->perItemRate) ? $ruleCategory->perItemRate : null,
                        'weightRate' => is_numeric($ruleCategory->weightRate) ? $ruleCategory->weightRate : null,
                        'percentageRate' => is_numeric($ruleCategory->percentageRate) ? $ruleCategory->percentageRate : null
                    ];
                    Craft::$app->getDb()->createCommand()->insert('commerce_shippingrule_categories', $data);
                } else {
                    $data = [
                        'shippingRuleId' => $model->id,
                        'shippingCategoryId' => $shippingCategory->id,
                        'condition' => ShippingRuleCategoryRecord::CONDITION_ALLOW
                    ];
                    Craft::$app->getDb()->createCommand()->insert('commerce_shippingrule_categories', $data);
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
            Craft::$app->getDb()->createCommand()->update('commerce_shippingrules',
                ['priority' => $sortOrder + 1], ['id' => $id]);
        }

        return true;
    }

    /**
     * @param int $id
     */
    public function deleteShippingRuleById($id)
    {
        ShippingRuleRecord::model()->deleteByPk($id);
    }
}
