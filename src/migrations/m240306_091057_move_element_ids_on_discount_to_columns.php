<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query as Query;
use craft\helpers\Json;

/**
 * m240306_091057_move_element_ids_on_discount_to_columns migration.
 */
class m240306_091057_move_element_ids_on_discount_to_columns extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $discountCategoriesTable = '{{%commerce_discount_categories}}';
        $discountPurchasablesTables = '{{%commerce_discount_purchasables}}';
        $discountsTable = '{{%commerce_discounts}}';

        $this->addColumn($discountsTable, 'purchasableIds', $this->text()->after('allPurchasables'));
        $this->addColumn($discountsTable, 'categoryIds', $this->text()->after('allCategories'));

        $purchasableIdsByDiscountId = (new Query())
            ->select(['discountId', 'purchasableId'])
            ->from([$discountPurchasablesTables])
            ->collect();

        $purchasableIdsByDiscountId = $purchasableIdsByDiscountId->groupBy('discountId')->map(function($row) {
            return array_column($row->toArray(), 'purchasableId');
        });

        $categoryIdsByDiscountId = (new Query())
            ->select(['discountId', 'categoryId'])
            ->from([$discountCategoriesTable])
            ->collect();

        $categoryIdsByDiscountId = $categoryIdsByDiscountId->groupBy('discountId')->map(function($row) {
            return array_column($row->toArray(), 'categoryId');
        });

        foreach ($purchasableIdsByDiscountId as $discountId => $purchasableIds) {
            $this->update($discountsTable, ['purchasableIds' => Json::encode($purchasableIds)], ['id' => $discountId]);
        }

        foreach ($categoryIdsByDiscountId as $discountId => $categoryIds) {
            $this->update($discountsTable, ['categoryIds' => Json::encode($categoryIds)], ['id' => $discountId]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240306_091057_move_element_ids_on_discount_to_columns cannot be reverted.\n";
        return false;
    }
}
