<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;

/**
 * m191114_115600_remove_customer_info_field migration.
 */
class m191114_115600_remove_customer_info_field extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        /**
         * Removed automatic deletion of customer info field. Due to potential conflicts with
         * project config. Forcing manual removal of the field gives better control and
         * understanding about what is happening.
         */
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191114_115600_remove_customer_info_field cannot be reverted.\n";
        return false;
    }
}
