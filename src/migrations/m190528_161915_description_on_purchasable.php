<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m190528_161915_description_on_purchasable migration.
 */
class m190528_161915_description_on_purchasable extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_purchasables}}', 'description', $this->text());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190528_161915_description_on_purchasable cannot be reverted.\n";
        return false;
    }
}
