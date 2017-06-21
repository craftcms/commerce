<?php

namespace craft\commerce\migrations;

use craft\commerce\records\ProductTypeSite;
use craft\db\Migration;
use craft\db\Query;

/**
 * m160531_154500_craft3_upgrade migration.
 */
class m170616_154500_productTypeSites_upgrade extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {

        $this->addColumn(ProductTypeSite::tableName(), 'template', $this->string(500).' AFTER urlFormat');
        $this->addColumn(ProductTypeSite::tableName(), 'hasUrls', $this->boolean().' AFTER siteId');

        // Migrate hasUrls to be site specific
        $productTypes = (new Query())->select('id, hasUrls, template')->from('{{%commerce_productTypes}}')->all();
        foreach ($productTypes as $productType)
        {
            $productTypeSites = (new Query())->select('*')->from('{{%commerce_productTypes_i18n}}')->all();
            foreach ($productTypeSites as $productTypeSite)
            {
                $productTypeSite['template'] = $productType['template'];
                $productTypeSite['hasUrls'] = $productType['hasUrls'];
                $this->update('{{%commerce_productTypes_i18n}}', $productTypeSite, [ 'id' => $productTypeSite['id']]);
            }
        }

        $this->dropColumn('{{%commerce_productTypes}}', 'template');
        $this->dropColumn('{{%commerce_productTypes}}', 'hasUrls');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170616_154500_productTypeSites_upgrade cannot be reverted.\n";


        return false;
    }
}
