<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m200218_231199_add_appliesTo_to_discounts migration.
 */
class m200218_231199_add_appliesTo_to_discounts extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $values = ['matchingLineItems', 'allLineItems'];

        if (!$this->db->columnExists('{{%commerce_discounts}}', 'appliedTo')) {
            $this->addColumn('{{%commerce_discounts}}', 'appliedTo', $this->enum('appliedTo', $values)->notNull()->defaultValue('matchingLineItems'));
        }

        // We want people off the base percentage discounts, so lets migrate them to
        // an 'all line items' per item percentage off if they aren't using that option.
        $discounts = (new Query())
            ->select('*')
            ->from('{{%commerce_discounts}}')
            ->where(
                ['baseDiscountType' => ['percentTotal', 'percentTotalDiscounted', 'percentItems', 'percentItemsDiscounted']]
            )
            ->andWhere(['[[percentDiscount]]' => 0])
            ->andWhere(['[[perItemDiscount]]' => 0])
            ->andWhere(['!=', '[[baseDiscount]]', 0])
            ->limit(null)
            ->all();

        $alreadyDone = [];
        foreach ($discounts as $discount) {

            $values = [
                'percentDiscount' => $discount['baseDiscount'] / 100, //base discount was stored in whole amounts
                'baseDiscount' => 0, // moved this to the percentDiscount above
                'baseDiscountType' => 'value', // put them back to value based whole order discount
                'appliedTo' => 'allLineItems', // put them back to value based whole order discount
            ];
            $alreadyDone[] = $discount['id'];
            $this->update('{{%commerce_discounts}}', $values, ['id' => $discount['id']]);
        }


        // Any that we missed that have a zero value for baseDiscount and can have their baseDiscountType reset to value only?
        $discounts = (new Query())
            ->select('*')
            ->from('{{%commerce_discounts}}')
            ->where(['[[baseDiscount]]' => 0])
            ->andWhere(['not', ['id' => array_values($alreadyDone)]]) // Let's not do any that have already been done
            ->limit(null)
            ->all();

        foreach ($discounts as $discount) {
            $values = [
                'baseDiscountType' => 'value', // put them back to value based whole order discount
            ];
            $this->update('{{%commerce_discounts}}', $values, ['id' => $discount['id']]);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200218_231199_add_appliesTo_to_discounts cannot be reverted.\n";
        return false;
    }
}
