<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\ShippingRule;
use craft\commerce\models\ShippingRuleCategory;
use craft\commerce\Plugin;
use craft\commerce\records\ShippingRule as ShippingRuleRecord;
use craft\commerce\records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use yii\base\Component;
use yii\base\Exception;

/**
 * Shipping rule service.
 *
 * @property ShippingRule $liteShippingRule The lite shipping rule
 * @property ShippingRule[] $allShippingRules
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingRules extends Component
{
    /**
     * @var null|ShippingRule[]
     */
    private $_allShippingRules;


    /**
     * Get all shipping rules.
     *
     * @return ShippingRule[]
     */
    public function getAllShippingRules(): array
    {
        if ($this->_allShippingRules !== null) {
            return $this->_allShippingRules;
        }

        $results = $this->_createShippingRulesQuery()->all();
        $this->_allShippingRules = [];

        foreach ($results as $result) {
            $this->_allShippingRules[] = new ShippingRule($result);
        }

        return $this->_allShippingRules;
    }

    /**
     * Get all shipping rules by a shipping method ID.
     *
     * @param int $id
     * @return ShippingRule[]
     */
    public function getAllShippingRulesByShippingMethodId($id): array
    {
        return ArrayHelper::where($this->getAllShippingRules(), 'methodId', $id);
    }

    /**
     * Get a shipping rule by its ID.
     *
     * @param int $id
     * @return ShippingRule|null
     */
    public function getShippingRuleById($id)
    {
        return ArrayHelper::firstWhere($this->getAllShippingRules(), 'id', $id);
    }

    /**
     * Save a shipping rule.
     *
     * @param ShippingRule $model
     * @param bool $runValidation should we validate this rule before saving.
     * @return bool
     * @throws Exception
     */
    public function saveShippingRule(ShippingRule $model, bool $runValidation = true): bool
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

        if ($runValidation && !$model->validate()) {
            Craft::info('Shipping rule not saved due to validation error.', __METHOD__);

            return false;
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
            'maxRate',
            'isLite'
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

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;

        ShippingRuleCategoryRecord::deleteAll(['shippingRuleId' => $model->id]);

        // Generate a rule category record for all categories regardless of data submitted
        foreach (Plugin::getInstance()->getShippingCategories()->getAllShippingCategories() as $shippingCategory) {
            if (isset($model->getShippingRuleCategories()[$shippingCategory->id]) && $ruleCategory = $model->getShippingRuleCategories()[$shippingCategory->id]) {
                $ruleCategory = new ShippingRuleCategory([
                    'shippingRuleId' => $model->id,
                    'shippingCategoryId' => $shippingCategory->id,
                    'condition' => $ruleCategory->condition,
                    'perItemRate' => $ruleCategory->perItemRate,
                    'weightRate' => $ruleCategory->weightRate,
                    'percentageRate' => $ruleCategory->percentageRate
                ]);
            } else {
                $ruleCategory = new ShippingRuleCategory([
                    'shippingRuleId' => $model->id,
                    'shippingCategoryId' => $shippingCategory->id,
                    'condition' => ShippingRuleCategoryRecord::CONDITION_ALLOW
                ]);
            }

            Plugin::getInstance()->getShippingRuleCategories()->createShippingRuleCategory($ruleCategory, $runValidation);
        }

        $this->_allShippingRules = null; // clear cache

        return true;
    }

    /**
     * Save a shipping rule.
     *
     * @param ShippingRule $model
     * @param bool $runValidation should we validate this rule before saving.
     * @return bool
     * @throws Exception
     */
    public function saveLiteShippingRule(ShippingRule $model, bool $runValidation = true): bool
    {
        $model->isLite = true;
        $model->id = null;

        // Delete the current lite shipping rule.
        Craft::$app->getDb()->createCommand()
            ->delete(ShippingRuleRecord::tableName(), ['isLite' => true])
            ->execute();

        $this->_allShippingRules = null; // clear cache
        return $this->saveShippingRule($model, $runValidation);
    }

    /**
     * Gets the the lite shipping rule or returns a new one.
     *
     * @return ShippingRule
     */
    public function getLiteShippingRule(): ShippingRule
    {
        $liteRule = $this->_createShippingRulesQuery()->one();

        if ($liteRule == null) {
            $liteRule = new ShippingRule();
            $liteRule->isLite = true;
            $liteRule->name = 'Shipping Cost';
            $liteRule->description = 'Shipping Cost';
            $liteRule->enabled = true;
        } else {
            $liteRule = new ShippingRule($liteRule);
        }

        $this->_allShippingRules = null; // clear cache

        return $liteRule;
    }

    /**
     * Reorders shipping rules by the given array of IDs.
     *
     * @param array $ids
     * @return bool
     */
    public function reorderShippingRules(array $ids): bool
    {
        foreach ($ids as $sortOrder => $id) {
            Craft::$app->getDb()->createCommand()->update(Table::SHIPPINGRULES, ['priority' => $sortOrder + 1], ['id' => $id])->execute();
        }
        $this->_allShippingRules = null; // clear cache

        return true;
    }

    /**
     * Deletes a shipping rule by an ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteShippingRuleById($id): bool
    {
        $record = ShippingRuleRecord::findOne($id);

        if ($record) {
            return (bool)$record->delete();
        }

        $this->_allShippingRules = null; // clear cache

        return false;
    }

    /**
     * Returns a Query object prepped for retrieving shipping rules.
     *
     * @return Query
     */
    private function _createShippingRulesQuery(): Query
    {
        $query = (new Query())
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
                'isLite'
            ])
            ->orderBy(['methodId' => SORT_ASC, 'priority' => SORT_ASC])
            ->from([Table::SHIPPINGRULES]);

        if (Plugin::getInstance()->is(Plugin::EDITION_LITE)) {
            $query->andWhere('[[isLite]] = true');
        }

        return $query;
    }
}
