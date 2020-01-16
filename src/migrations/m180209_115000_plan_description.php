<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use craft\db\Migration;

/**
 * m180209_115000_plan_description
 */
class m180209_115000_plan_description extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->addColumn('{{%commerce_plans}}', 'planInformationId', $this->integer()->null());
        $this->addForeignKey(null, '{{%commerce_plans}}', 'planInformationId', '{{%elements}}', 'id', 'SET NULL');

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m180209_115000_plan_description cannot be reverted.\n";

        return false;
    }
}
