<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\commerce\records\ProductTypeSite;
use craft\db\Migration;
use craft\helpers\MigrationHelper;

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
        MigrationHelper::renameTable('{{%commerce_producttypes_i18n}}', ProductTypeSite::tableName(), $this);
        MigrationHelper::renameColumn(ProductTypeSite::tableName(), 'urlFormat', 'uriFormat', $this);

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
