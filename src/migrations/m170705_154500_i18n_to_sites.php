<?php

namespace craft\commerce\migrations;

use craft\commerce\records\ProductTypeSite;
use craft\db\Migration;
use craft\db\Query;

/**
 * m170705_154500_i18n_to_sites migration.
 */
class m170705_154500_i18n_to_sites extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->renameTable('{{%commerce_producttypes_i18n}}', ProductTypeSite::tableName());
        $this->renameColumn(ProductTypeSite::tableName(), 'urlFormat', 'uriFormat');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170705_154500_i18n_to_sites cannot be reverted.\n";


        return false;
    }
}
