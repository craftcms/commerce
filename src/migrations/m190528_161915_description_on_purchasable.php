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

            if ($variant) {
                $productTypeId = (new Query())
                    ->select(['[[products.typeId]]'])
                    ->from(['{{%commerce_products}} products'])
                    ->leftJoin('{{%commerce_variants}} variants', '[[variants.productId]] = [[products.id]]')
                    ->where('[[variants.id]] = 12')
                    ->scalar();

                if ($productTypeId) {
                    $productTypeDescriptionFormat = (new Query())
                        ->select(['[[producttypes.descriptionFormat]]'])
                        ->from(['{{%commerce_producttypes}} producttypes'])
                        ->where('[[producttypes.id]] = ' . $productTypeId)
                        ->scalar();

                    try {
                        $description = Craft::$app->getView()->renderObjectTemplate($productTypeDescriptionFormat, $variant);
                        $this->update('{{%commerce_purchasables}}', ['description' => $description], ['id' => $variantId]);
                    } catch (\Exception $e) {
                        // A Re-save or variants will update the purchasable descriptions - so don't worry about it.
                    }
                }
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
