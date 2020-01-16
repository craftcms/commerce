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
 * m191008_153947_add_relationship_type_to_discounts migration.
 */
class m191008_153947_add_relationship_type_to_discounts extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $columnName = 'categoryRelationshipType';
        $values = ['element', 'sourceElement', 'targetElement'];

        $this->addColumn('{{%commerce_discounts}}', $columnName, $this->enum($columnName, $values)->notNull()->defaultValue('element'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191008_153947_add_relationship_type_to_discounts cannot be reverted.\n";
        return false;
    }
}
