<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\ShippingRuleCategory;
use craft\commerce\models\ShippingCategory;
use craft\commerce\models\ShippingRule;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingRule as ShippingRuleRecord;
use craft\commerce\records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use craft\db\Query;
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
     * @var bool
     */
    private $_fetchedAllShippingRules = false;

    /**
     * @var ShippingRule[]
     */
    private $_allShippingRules = [];

    /**
     * @var ShippingRule[][]
     */
    private $_shippingRulesByMethodId = [];

    /**
     * @return ShippingRule[]
     */
    public function getAllShippingRules(): array
    {
        if (!$this->_fetchedAllShippingRules) {
            $this->_fetchedAllShippingRules = true;
            $rows = $this->_createShippingRulesQuery()->all();

            foreach ($rows as $row) {
                $this->_allShippingRules[$row['id']] = new ShippingRule($row);
            }
        }

        return $this->_allShippingRules;
    }
    
    /**
     * @param int $id
     *
     * @return ShippingRule[]
     */
    public function getAllShippingRulesByShippingMethodId($id)
    {
        if (isset($this->_shippingRulesByMethodId[$id])) {
            return $this->_shippingRulesByMethodId[$id];
        }

        if ($this->_fetchedAllShippingRules) {
            return null;
        }

        $results = $this->_createShippingRulesQuery()
            ->where(['methodId' => $id])
            ->orderBy('priority')
            ->all();

        $rules = [];

        foreach ($results as $row) {
            $rule = new ShippingRule($row);
            $rules[] = $rule;
            $this->_allShippingRules[$row['id']] = $rule;
        }

        $this->_shippingRulesByMethodId[$id] = $rules;

        return $rules;
    }

    /**
     * @param int $id
     *
     * @return ShippingRule|null
     */
    public function getShippingRuleById($id)
    {
        if (isset($this->_allShippingRules[$id])) {
            return $this->_allShippingRules[$id];
        }

        if ($this->_fetchedAllShippingRules) {
            return null;
        }

        $row = $this->_createShippingRulesQuery()
            ->where(['id' => $id])
            ->one();

        if (!$row) {
            return null;
        }

        return $this->_allShippingRules[$id] = new ShippingRule($row);
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
            $record = ShippingRuleRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'No shipping rule exists with the ID “{id}”',
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

        $record->shippingZoneId = $model->shippingZoneId ?: null;

        if (empty($record->priority) && empty($model->priority)) {
            $count = ShippingRuleRecord::find()->where(['methodId' => $model->methodId])->count();
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

            ShippingRuleCategoryRecord::deleteAll(['shippingRuleId' => $model->id]);

            // Generate a rule category record for all categories regardless of data submitted
            foreach (Plugin::getInstance()->getShippingCategories()->getAllShippingCategories() as $shippingCategory) {
                /** @var ShippingCategory $ruleCategory */
                if (isset($model->getShippingRuleCategories()[$shippingCategory->id]) && $ruleCategory = $model->getShippingRuleCategories()[$shippingCategory->id]) {
                    $ruleCategory = new ShippingRuleCategory([
                            'shippingRuleId' => $model->id,
                            'shippingCategoryId' => $shippingCategory->id,
                            'condition' => $ruleCategory->condition,
                            'perItemRate' => is_numeric($ruleCategory->perItemRate) ? $ruleCategory->perItemRate : null,
                            'weightRate' => is_numeric($ruleCategory->weightRate) ? $ruleCategory->weightRate : null,
                            'percentageRate' => is_numeric($ruleCategory->percentageRate) ? $ruleCategory->percentageRate : null
                    ]);
                } else {
                    $ruleCategory = new ShippingRuleCategory([
                        'shippingRuleId' => $model->id,
                        'shippingCategoryId' => $shippingCategory->id,
                        'condition' => ShippingRuleCategoryRecord::CONDITION_ALLOW
                    ]);
                }

                Plugin::getInstance()->getShippingRuleCategories()->createShippingRuleCategory($ruleCategory);
            }

            return true;
        }

        return false;
    }

    /**
     * @param $ids
     *
     * @return bool
     */
    public function reorderShippingRules($ids)
    {
        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()->update('commerce_shippingrules', ['priority' => $sortOrder + 1], ['id' => $id])->execute();
        }

        return true;
    }

    /**
     * @param int $id
     * 
     * @return bool
     */
    public function deleteShippingRuleById($id): bool
    {
        $record = ShippingRuleRecord::findOne($id);

        if ($record) {
            return (bool)$record->delete();
        }

        return false;
    }

    // Private methods
    // =========================================================================
    /**
     * Returns a Query object prepped for retrieving shipping rules.
     *
     * @return Query
     */
    private function _createShippingRulesQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'shippingZoneId',
                'name',
                'description',
                'methodId',
                'priority',
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
                'maxRate',
            ])
            ->orderBy('name')
            ->from(['{{%commerce_shippingrules}}']);
    }
}
