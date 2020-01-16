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
 * m191015_201444_add_code_to_taxRates migration.
 */
class m191015_201444_add_code_to_taxRates extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_taxrates}}', 'code', $this->string()->after('name'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191015_201444_add_code_to_taxRates cannot be reverted.\n";
        return false;
    }
}
