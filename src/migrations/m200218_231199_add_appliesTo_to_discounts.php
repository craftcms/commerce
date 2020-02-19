<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m200218_231199_add_appliesTo_to_discounts migration.
 */
class m200218_231199_add_appliesTo_to_discounts extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $values = ['matchingLineItems', 'allLineItems'];
        $this->addColumn('{{%commerce_discounts}}', 'appliedTo', $this->enum('appliedTo', $values)->notNull()->defaultValue('matchingLineItems'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200218_231199_add_appliesTo_to_discounts cannot be reverted.\n";
        return false;
    }
}
