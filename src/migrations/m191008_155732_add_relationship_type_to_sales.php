<?php

namespace craft\commerce\migrations;

use Craft;
use craft\db\Migration;

/**
 * m191008_155732_add_relationship_type_to_sales migration.
 */
class m191008_155732_add_relationship_type_to_sales extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $columnName = 'categoryRelationshipType';
        $values = ['sourceElement', 'targetElement', 'element'];

        $this->addColumn('{{%commerce_sales}}', $columnName, $this->enum($columnName, $values)->after('allCategories')->notNull()->defaultValue('sourceElement'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m191008_155732_add_relationship_type_to_sales cannot be reverted.\n";
        return false;
    }
}
