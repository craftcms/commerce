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
 * m191202_220748_updated_zipCodeConditional_column_type migration.
 */
class m191202_220748_updated_zipCodeConditional_column_type extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn('{{%commerce_shippingzones}}', 'zipCodeConditionFormula', $this->text());
        $this->alterColumn('{{%commerce_taxzones}}', 'zipCodeConditionFormula', $this->text());
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191202_220748_updated_zipCodeConditional_column_type cannot be reverted.\n";
        return false;
    }
}
