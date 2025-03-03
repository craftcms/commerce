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
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\StaleObjectException;

/**
 * Shipping rule service.
 *
 * @property ShippingRule[] $allShippingRules
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingRules extends Component
{
    /**
     * @var null|Collection<ShippingRule>
     */
    private ?Collection $_allShippingRules = null;

    /**
     * Get all shipping rules.
     *
     * @return Collection<ShippingRule>
     * @throws InvalidConfigException
     */
    public function getAllShippingRules(): Collection
    {
        // @TODO figure out if we need to memoize this
        if ($this->_allShippingRules !== null) {
            return $this->_allShippingRules;
        }

        $results = $this->_createShippingRulesQuery()->all();
        $allShippingRules = [];

        foreach ($results as $result) {
            $result['orderCondition'] = $result['orderCondition'] ?? '';
            $allShippingRules[] = Craft::createObject([
                'class' => ShippingRule::class,
                'attributes' => $result,
            ]);
        }

        $this->_allShippingRules = collect($allShippingRules);

        return $this->_allShippingRules;
    }

    /**
     * Get all shipping rules by a shipping method ID.
     *
     * @param int $id
     * @return Collection
     * @throws InvalidConfigException
     */
    public function getAllShippingRulesByShippingMethodId(int $id): Collection
    {
        return $this->getAllShippingRules()->where('methodId', $id);
    }

    /**
     * Get a shipping rule by its ID.
     */
    public function getShippingRuleById(int $id): ?ShippingRule
    {
        return $this->getAllShippingRules()->firstWhere('id', $id);
    }

    /**
     * Save a shipping rule.
     *
     * @param bool $runValidation should we validate this rule before saving.
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
            'orderConditionFormula',
            'baseRate',
            'perItemRate',
            'weightRate',
            'percentageRate',
            'minRate',
            'maxRate',
        ];
        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        $record->orderCondition = $model->getOrderCondition()->getConfig();

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
        foreach (Plugin::getInstance()->getShippingCategories()->getAllShippingCategories($model->storeId) as $shippingCategory) {
            $ruleCategory = $model->getShippingRuleCategories()[$shippingCategory->id] ?? null;
            if ($ruleCategory) {
                $ruleCategory = new ShippingRuleCategory([
                    'shippingRuleId' => $model->id,
                    'shippingCategoryId' => $shippingCategory->id,
                    'condition' => $ruleCategory->condition,
                    'perItemRate' => $ruleCategory->perItemRate,
                    'weightRate' => $ruleCategory->weightRate,
                    'percentageRate' => $ruleCategory->percentageRate,
                ]);
            } else {
                $ruleCategory = new ShippingRuleCategory([
                    'shippingRuleId' => $model->id,
                    'shippingCategoryId' => $shippingCategory->id,
                    'condition' => ShippingRuleCategoryRecord::CONDITION_ALLOW,
                ]);
            }

            Plugin::getInstance()->getShippingRuleCategories()->createShippingRuleCategory($ruleCategory, $runValidation);
        }

        $this->_allShippingRules = null; // clear cache

        return true;
    }

    /**
     * Reorders shipping rules by the given array of IDs.
     *
     * @throws \yii\db\Exception
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
     * @throws Throwable
     * @throws StaleObjectException
     */
    public function deleteShippingRuleById(int $id): bool
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
     */
    private function _createShippingRulesQuery(): Query
    {
        $query = (new Query())
            ->select([
                'shippingrules.baseRate',
                'shippingrules.description',
                'shippingrules.enabled',
                'shippingrules.id',
                'shippingrules.maxRate',
                'shippingrules.methodId',
                'shippingrules.minRate',
                'shippingrules.name',
                'shippingrules.orderConditionFormula',
                'shippingrules.orderCondition',
                'shippingrules.percentageRate',
                'shippingrules.perItemRate',
                'shippingrules.priority',
                'shippingrules.weightRate',
                'methods.storeId',
            ])
            ->orderBy(['methodId' => SORT_ASC, 'priority' => SORT_ASC])
            ->from(Table::SHIPPINGRULES . ' shippingrules')
            ->innerJoin(Table::SHIPPINGMETHODS . ' methods', '[[methods.id]] = [[shippingrules.methodId]]');

        return $query;
    }
}
