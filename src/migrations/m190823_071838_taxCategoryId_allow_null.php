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
 * m190823_071838_taxCategoryId_allow_null migration.
 */
class m190823_071838_taxCategoryId_allow_null extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn('{{%commerce_taxrates}}', 'taxCategoryId', $this->integer()->null());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190823_071838_taxCategoryId_allow_null cannot be reverted.\n";
        return false;
    }
}
