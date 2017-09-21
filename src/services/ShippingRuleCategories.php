<?php

namespace craft\commerce\services;

use craft\commerce\models\ShippingRuleCategory;
use craft\commerce\records\ShippingRuleCategory as ShippingRuleCategoryRecord;
use craft\db\Query;
use yii\base\Component;

/**
 * Shipping rule categories service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class ShippingRuleCategories extends Component
{
    // Properties
    // =========================================================================

    /**
     * @var ShippingRuleCategory[][]
     */
    private $_shippingRuleCategoriesByRuleId = [];

    // Public Methods
    // =========================================================================

    /**
     * Return an array of shipping rules categories by a rule id.
     *
     * @param int $ruleId The rule id.
     *
     * @return ShippingRuleCategory[] An array of matched shipping rule categories.
     */
    public function getShippingRuleCategoriesByRuleId(int $ruleId): array
    {
        if (!isset($this->_shippingRuleCategoriesByRuleId[$ruleId])) {
            $rows = $this->_createShippingRuleCategoriesQuery()
                ->where(['shippingRuleId' => $ruleId])
                ->all();

            $this->_shippingRuleCategoriesByRuleId[$ruleId] = [];
            foreach ($rows as $row) {
                $this->_shippingRuleCategoriesByRuleId[$ruleId][] = new ShippingRuleCategory($row);
            }
        }

        return $this->_shippingRuleCategoriesByRuleId[$ruleId];
    }

    /**
     * Save a shipping rule category.
     *
     * @param ShippingRuleCategory $model The shipping rule model.
     *
     * @return bool Whether the save was successful.
     */
    public function createShippingRuleCategory(ShippingRuleCategory $model): bool
    {
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

        $record->validate();
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
     * Delete a shipping rule category by it's id.
     *
     * @param int $id The shipping rule category id.
     * 
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

    // Private methods
    // =========================================================================

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
            ->from(['{{%commerce_shippingrule_categories}}']);
    }
}
