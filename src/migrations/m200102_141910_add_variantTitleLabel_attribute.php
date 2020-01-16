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
 * m200102_141910_add_variantTitleLabel_attribute migration.
 */
class m200102_141910_add_variantTitleLabel_attribute extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_producttypes}}', 'variantTitleLabel', $this->string()->defaultValue('Title'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200102_141910_add_variantTitleLabel_attribute cannot be reverted.\n";
        return false;
    }
}
