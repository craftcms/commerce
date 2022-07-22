<?php

namespace craft\commerce\migrations;

use craft\commerce\services\Coupons;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\StringHelper;
use yii\db\Expression;

/**
 * m211118_101920_split_coupon_codes migration.
 */
class m211118_101920_split_coupon_codes extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->getDb()->tableExists('{{%commerce_coupons}}')) {
            $this->createTable('{{%commerce_coupons}}', [
                'id' => $this->primaryKey(),
                'code' => $this->string(),
                'discountId' => $this->integer()->notNull(),
                'uses' => $this->integer()->notNull()->defaultValue(0),
                'maxUses' => $this->integer(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%commerce_coupons}}', 'discountId', false);
            $this->createIndex(null, '{{%commerce_coupons}}', 'code', false);

            $this->addForeignKey(null, '{{%commerce_coupons}}', ['discountId'], '{{%commerce_discounts}}', ['id'], 'CASCADE', 'CASCADE');
        }

        if (!$this->db->columnExists('{{%commerce_discounts}}', 'couponFormat')) {
            $this->addColumn('{{%commerce_discounts}}', 'couponFormat', $this->string(20)->notNull()->defaultValue(Coupons::DEFAULT_COUPON_FORMAT));
        }

        if (!(new Query())->from('{{%commerce_coupons}}')->exists()) {
            // These could be one query, leaving as separate for now for readability
            $discountsWithCodes = (new Query())
                ->select(['id', 'code', 'totalDiscountUseLimit', 'dateCreated', 'dateUpdated'])
                ->from('{{%commerce_discounts}}')
                ->where(['not', ['code' => null]])
                ->all();

            $codeUsage = (new Query())
                ->select([new Expression('COUNT(*) as count'), 'couponCode as code'])
                ->from('{{%commerce_orders}}')
                ->where(['not', ['couponCode' => null]])
                ->groupBy('couponCode')
                ->indexBy('code')
                ->column();

            if (!empty($discountsWithCodes)) {
                $coupons = array_map(static function($discount) use ($codeUsage) {
                    $row['code'] = $discount['code'];
                    $row['discountId'] = $discount['id'];
                    $row['uses'] = $codeUsage[$discount['code']] ?? 0;
                    $row['maxUses'] = $discount['totalDiscountUseLimit'] ?? 0;
                    $row['dateCreated'] = $discount['dateCreated'];
                    $row['dateUpdated'] = $discount['dateUpdated'];
                    $row['uid'] = StringHelper::UUID();

                    return $row;
                }, $discountsWithCodes);

                $this->batchInsert('{{%commerce_coupons}}', [
                    'code',
                    'discountId',
                    'uses',
                    'maxUses',
                    'dateCreated',
                    'dateUpdated',
                    'uid',
                ], $coupons);
            }
        }

        if ($this->db->columnExists('{{%commerce_discounts}}', 'code')) {
            $this->dropIndexIfExists('{{%commerce_discounts}}', 'code', true);
            $this->dropColumn('{{%commerce_discounts}}', 'code');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m211118_101920_split_coupon_codes cannot be reverted.\n";
        return false;
    }
}
