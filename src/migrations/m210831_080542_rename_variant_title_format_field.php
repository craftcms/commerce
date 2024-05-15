<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m210831_080542_rename_variant_title_format_field migration.
 */
class m210831_080542_rename_variant_title_format_field extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->renameColumn('{{%commerce_producttypes}}', 'titleFormat', 'variantTitleFormat');

        $projectConfig = Craft::$app->getProjectConfig();

        $productTypes = $projectConfig->get('commerce.productTypes') ?? [];
        $muteEvents = $projectConfig->muteEvents;
        $projectConfig->muteEvents = true;

        foreach ($productTypes as $uid => $productType) {
            $productType['variantTitleFormat'] = $productType['titleFormat'];
            unset($productType['titleFormat']);
            $projectConfig->set("commerce.productTypes.$uid", $productType);
        }

        $projectConfig->muteEvents = $muteEvents;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210831_080542_rename_variant_title_format_field cannot be reverted.\n";
        return false;
    }
}
