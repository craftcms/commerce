<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\services\ProductTypes;
use craft\db\Migration;
use craft\db\Query;

/**
 * m230103_122549_add_product_type_max_variants migration.
 */
class m230103_122549_add_product_type_max_variants extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%commerce_producttypes}}', 'maxVariants')) {
            $this->addColumn('{{%commerce_producttypes}}', 'maxVariants', $this->integer());
        }

        if ($this->db->columnExists('{{%commerce_producttypes}}', 'hasVariants')) {
            $this->update('{{%commerce_producttypes}}', ['maxVariants' => 1], ['hasVariants' => false]);

            $this->updateProjectConfig();

            $this->dropColumn('{{%commerce_producttypes}}', 'hasVariants');
        }

        return true;
    }

    private function updateProjectConfig(): void
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $projectConfig->muteEvents = true;

        $maxVariantProductTypes = (new Query())
            ->select(['id', 'maxVariants', 'uid'])
            ->from(['{{%commerce_producttypes}}'])
            ->all();

        foreach ($maxVariantProductTypes as $productType) {
            $config = $projectConfig->get(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.' . $productType['uid']);
            if (array_key_exists('hasVariants', $config)) {
                unset($config['hasVariants']);
            }

            $config['maxVariants'] = $productType['maxVariants'];
            $projectConfig->set(ProductTypes::CONFIG_PRODUCTTYPES_KEY . '.' . $productType['uid'], $config);
        }

        $projectConfig->muteEvents = false;
    }


    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m230103_122549_add_product_type_max_variants cannot be reverted.\n";
        return false;
    }
}
