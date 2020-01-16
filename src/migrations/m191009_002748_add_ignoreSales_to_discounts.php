<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191009_002748_add_ignoreSales_to_discounts migration.
 */
class m191009_002748_add_ignoreSales_to_discounts extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_discounts}}', 'ignoreSales', $this->boolean()->after('stopProcessing')->notNull()->defaultValue(false));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191009_002748_add_ignoreSales_to_discounts cannot be reverted.\n";
        return false;
    }
}
