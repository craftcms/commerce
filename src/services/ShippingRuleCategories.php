<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\models\ShippingRuleCategory;
use craft\commerce\records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use craft\db\Query;
use Throwable;
use yii\base\Component;
use yii\db\StaleObjectException;

/**
 * Shipping rule categories service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingRuleCategories extends Component
{
    private ?array $_shippingRuleCategories = null;

    public function getAllShippingRuleCategoriesData(): array
    {
        if ($this->_shippingRuleCategories === null) {
            $data = $this->_createShippingRuleCategoriesQuery()->all();

            if (!empty($data)) {
                $ruleCategories = [];
                foreach ($data as $row) {
                    if (!isset($ruleCategories[$row['shippingRuleId']])) {
                        $ruleCategories[$row['shippingRuleId']] = [];
                    }

                    $ruleCategories[$row['shippingRuleId']][$row['shippingCategoryId']] = $row;
                }

                $this->_shippingRuleCategories = $ruleCategories;
            }
        }

        return $this->_shippingRuleCategories ?? [];
    }

    /**
     * Returns an array of shipping rules categories per the rule's ID.
     *
     * @param int $ruleId the rule's ID
     * @return ShippingRuleCategory[] An array of matched shipping rule categories.
     */
    public function getShippingRuleCategoriesByRuleId(int $ruleId): array
    {
        $rules = [];

        $shippingRuleCategories = $this->getAllShippingRuleCategoriesData();
        if (!isset($shippingRuleCategories[$ruleId])) {
            return [];
        }

        foreach ($shippingRuleCategories[$ruleId] as $row) {
            if ($row instanceof ShippingRuleCategory) {
                continue;
            }

            $id = $row['shippingCategoryId'];
            $rules[$id] = new ShippingRuleCategory($row);
        }

        $this->_shippingRuleCategories[$ruleId] = $rules;

        return $rules;
    }

    /**
     * Returns an array of shipping rule categories indexed by rule ID.
     *
     * @param int[] $ruleIds
     * @return array<int, ShippingRuleCategory[]>
     * @since 5.6.0
     */
    public function getShippingRuleCategoriesByRuleIds(array $ruleIds): array
    {
        if (empty($ruleIds)) {
            return [];
        }

        $categoriesByRuleId = [];

        $rows = $this->_createShippingRuleCategoriesQuery()
            ->where(['shippingRuleId' => $ruleIds])
            ->all();

        foreach ($rows as $row) {
            $ruleId = $row['shippingRuleId'];
            $categoryId = $row['shippingCategoryId'];
            $categoriesByRuleId[$ruleId][$categoryId] = new ShippingRuleCategory($row);
        }

        return $categoriesByRuleId;
    }

    /**
     * Save a shipping rule category.
     *
     * @param ShippingRuleCategory $model The shipping rule model.
     * @param bool $runValidation should we validate this rule category before saving.
     * @return bool Whether the save was successful.
     */
    public function createShippingRuleCategory(ShippingRuleCategory $model, bool $runValidation = true): bool
    {
        if ($runValidation && !$model->validate()) {
            Craft::info('Shipping rule category not saved due to validation error.', __METHOD__);

            return false;
        }

        $record = new ShippingRuleCategoryRecord();

        $fields = [
            'shippingRuleId',
            'shippingCategoryId',
            'condition',
            'perItemRate',
            'weightRate',
            'percentageRate',
        ];

        foreach ($fields as $field) {
            $record->$field = $model->$field;
        }

        // Save it!
        $record->save(false);

        // Now that we have a record ID, save it on the model
        $model->id = $record->id;

        return true;
    }

    /**
     * Delete a shipping rule category by its ID.
     *
     * @param int $id the shipping rule category ID.
     * @return bool Whether the category was deleted successfully.
     * @throws Throwable
     * @throws StaleObjectException
     * @noinspection PhpUnused
     */
    public function deleteShippingRuleCategoryById(int $id): bool
    {
        $record = ShippingRuleCategoryRecord::findOne($id);

        if ($record) {
            // Clear cache if required
            unset($this->_shippingRuleCategories[$record->shippingRuleId]);

            return (bool)$record->delete();
        }

        return false;
    }

    /**
     * Returns a Query object prepped for retrieving shipping rule categories.
     *
     * @return Query The query object.
     */
    private function _createShippingRuleCategoriesQuery(): Query
    {
        return (new Query())
            ->select([
                'condition',
                'id',
                'percentageRate',
                'perItemRate',
                'shippingCategoryId',
                'shippingRuleId',
                'weightRate',
            ])
            ->from([Table::SHIPPINGRULE_CATEGORIES]);
    }
}
