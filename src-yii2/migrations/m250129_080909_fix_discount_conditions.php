<?php

namespace craft\commerce\migrations;

use craft\commerce\db\Table;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

/**
 * m250129_080909_fix_discount_conditions migration.
 */
class m250129_080909_fix_discount_conditions extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $discounts = (new Query())
            ->select(['id', 'orderCondition', 'storeId'])
            ->from(Table::DISCOUNTS)
            ->all();

        foreach ($discounts as $discount) {
            $discountId = $discount['id'];
            $storeId = $discount['storeId'];
            $orderConditionData = Json::decodeIfJson($discount['orderCondition']);

            if (!is_array($orderConditionData)) {
                continue;
            }

            if (!isset($orderConditionData['storeId'])) {
                $orderConditionData['storeId'] = $storeId;
                $orderConditionJson = Json::encode($orderConditionData);
                $this->update(Table::DISCOUNTS,
                    ['orderCondition' => $orderConditionJson],
                    ['id' => $discountId]
                );
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250129_080909_fix_discount_conditions cannot be reverted.\n";
        return false;
    }
}
