<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m200218_231144_add_sortOrder_to_states migration.
 */
class m200218_231144_add_sortOrder_to_states extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->addColumn('{{%commerce_states}}', 'sortOrder', $this->integer()->after('abbreviation'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200218_231144_add_sortOrder_to_states cannot be reverted.\n";
        return false;
    }
}
