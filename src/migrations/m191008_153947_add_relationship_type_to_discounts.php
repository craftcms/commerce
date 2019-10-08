<?php

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
        $values = ['sourceElement', 'targetElement', 'element'];

        $this->addColumn('{{%commerce_discounts}}', $columnName, $this->enum($columnName, $values)->after('allCategories')->notNull()->defaultValue('sourceElement'));
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
