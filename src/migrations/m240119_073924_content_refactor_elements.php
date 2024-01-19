<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\migrations\BaseContentRefactorMigration;

/**
 * m240119_073924_content_refactor_elements migration.
 */
class m240119_073924_content_refactor_elements extends BaseContentRefactorMigration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Migrate order elements
        $this->updateElements(
            (new Query())->from(Table::ORDERS),
            Craft::$app->getFields()->getLayoutByType(Order::class)
        );

        // Migrate products and variants by product type
        foreach (Plugin::getInstance()->getProductTypes()->getAllProductTypes() as $productType) {
            // Update Products
            $this->updateElements(
                (new Query())->from(Table::PRODUCTS)->where(['typeId' => $productType->id]),
                $productType->getProductFieldLayout()
            );

            // Update Variants
            $this->updateElements(
                (new Query())->from(Table::VARIANTS)->where([
                    'primaryOwnerId' => (new Query())->select('id')->from(Table::PRODUCTS)->where(['typeId' => $productType->id])
                ]),
                $productType->getVariantFieldLayout()
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240119_073924_content_refactor_elements cannot be reverted.\n";
        return false;
    }
}
