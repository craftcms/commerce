<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m200722_172699_product_title_format migration.
 */
class m200722_172699_product_title_format extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (!$this->db->columnExists('{{%commerce_producttypes}}', 'hasProductTitleField')) {
            $this->addColumn('{{%commerce_producttypes}}', 'hasProductTitleField', $this->boolean());
        }

        if (!$this->db->columnExists('{{%commerce_producttypes}}', 'productTitleFormat')) {
            $this->addColumn('{{%commerce_producttypes}}', 'productTitleFormat', $this->string()->notNull());
        }

        // Don't make the same config changes twice
        $projectConfig = \Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.commerce.schemaVersion', true);
        if (version_compare($schemaVersion, '3.2.0', '<')) {
            foreach ($projectConfig->get('commerce.productTypes') ?? [] as $uid => $productType) {
                $productType['hasProductTitleField'] = true;
                $productType['productTitleFormat'] = '';
                $projectConfig->set("commerce.productTypes.{$uid}", $productType);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200722_172699_product_title_format cannot be reverted.\n";
        return false;
    }
}
