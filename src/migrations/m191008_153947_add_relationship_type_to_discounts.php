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
        $values = ['element', 'sourceElement', 'targetElement'];

        if ($this->db->getIsPgsql()) {
            // Manually construct the SQL for Postgres
            $check = '[['.$columnName.']] in (';
            foreach ($values as $i => $value) {
                if ($i != 0) {
                    $check .= ',';
                }
                $check .= $this->db->quoteValue($value);
            }
            $check .= ')';
            $this->execute("alter table {{%commerce_discounts}} drop constraint {{%commerce_discounts_".$columnName."_check}}, add check ({$check})");
        } else {
            $this->addColumn('{{%commerce_discounts}}', $columnName, $this->enum($columnName, $values)->notNull()->defaultValue('element'));
        }
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
