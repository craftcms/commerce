<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\elements\Variant;
use craft\db\Migration;
use craft\db\Query;

/**
 * m190528_161915_description_on_purchasable migration.
 */
class m190528_161915_description_on_purchasable extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_purchasables}}', 'description', $this->text());

        $variantIds = (new Query())
            ->select(['id'])
            ->from(['{{%commerce_variants}}'])
            ->column();

        foreach ($variantIds as $variantId) {
            $variant = Variant::find()->id($variantId)->one();
            // Just in case
            if ($variant) {
                $this->update('{{%commerce_purchasables}}', ['description' => $variant->getDescription()], ['id' => $variantId]);
            } else {
                // If there is no element for this variant log it
                Craft::error(['Variant ID not found in element table:', $variantId], 'commerce');
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190528_161915_description_on_purchasable cannot be reverted.\n";
        return false;
    }
}
