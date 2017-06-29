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
    /**
     * Return an array of shipping rules categories by a rule id.
     *
     * @param $id
     *
     * @return ShippingRuleCategory[]
     */
    public function getShippingRuleCategoryByRuleId($id): array
    {
        $result = $this->_createShippingRuleCategoriesQuery()
            ->where(['shippingRuleId' => $id])
            ->all();

        return ShippingRuleCategory::populateModels($result, 'shippingCategoryId');
    }

    /**
     * Save a shipping rule category.
     *
     * @param ShippingRuleCategory $model
     *
     * @return bool
     */
    public function saveShippingRuleCategory(ShippingRuleCategory $model)
    {

        $record = new ShippingRuleCategoryRecord();
        $fields = [
            'id',
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
     * @param int $id
     * 
     * @return bool
     */
    public function deleteShippingRuleCategoryById($id): bool
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
     * @return Query
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
