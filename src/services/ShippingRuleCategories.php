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
use yii\base\Component;

/**
 * Shipping rule categories service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingRuleCategories extends Component
{
    /**
     * @var ShippingRuleCategory[][]
     */
    private $_shippingRuleCategoriesByRuleId = [];


    /**
     * Returns an array of shipping rules categories per the rule's ID.
     *
     * @param int $ruleId the rule's ID
     * @return ShippingRuleCategory[] An array of matched shipping rule categories.
     */
    public function getShippingRuleCategoriesByRuleId(int $ruleId): array
    {
        if (empty($this->_shippingRuleCategoriesByRuleId)) {
            $rows = $this->_createShippingRuleCategoriesQuery()
                ->all();

            foreach ($rows as $row) {
                if (!isset($this->_shippingRuleCategoriesByRuleId[$row['shippingRuleId']])) {
                    $this->_shippingRuleCategoriesByRuleId[$row['shippingRuleId']] = [];
                }
                $this->_shippingRuleCategoriesByRuleId[$row['shippingRuleId']][$row['shippingCategoryId']] = new ShippingRuleCategory($row);
            }
        }

        return $this->_shippingRuleCategoriesByRuleId[$ruleId] ?? [];
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
     */
    public function deleteShippingRuleCategoryById(int $id): bool
    {
        $record = ShippingRuleCategoryRecord::findOne($id);

        if ($record) {
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
                'id',
                'shippingRuleId',
                'shippingCategoryId',
                'condition',
                'perItemRate',
                'weightRate',
                'percentageRate',
            ])
            ->from([Table::SHIPPINGRULE_CATEGORIES]);
    }
}
