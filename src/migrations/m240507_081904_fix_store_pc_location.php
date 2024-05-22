<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\services\Stores;
use craft\db\Migration;
use craft\db\Query;

/**
 * m240507_081904_fix_store_pc_location migration.
 */
class m240507_081904_fix_store_pc_location extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        $allStores = $projectConfig->get(Stores::CONFIG_STORES_KEY) ?? [];

        if (!empty($allStores)) {
            return true;
        }

        $storeUid = (new Query())->select('uid')->from(Table::STORES)->scalar();

        // Bad config key on purpose
        $badCommerceConfig = $projectConfig->get(Stores::CONFIG_STORES_KEY . $storeUid);

        if ($badCommerceConfig) {
            $projectConfig->set(Stores::CONFIG_STORES_KEY . '.' . $storeUid, $badCommerceConfig);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240507_081904_fix_store_pc_location cannot be reverted.\n";
        return false;
    }
}
